<?php

namespace App\Http\Controllers;

use App\Services\DbSync\DbSyncService;
use App\Services\DbSync\DbSyncStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class DbSyncStatusController extends Controller
{
    /** Role yang boleh menjalankan sinkron / menyelesaikan konflik dari dashboard. */
    public const MANAGER_ROLES = ['owner', 'superadmin'];

    /** Dipanggil kartu "Status Database Server Hosting" di dashboard (AJAX, supaya dashboard tidak ikut lambat saat hosting tidak terjangkau). */
    public function __invoke(Request $request, DbSyncStatus $status, DbSyncService $sync): JsonResponse
    {
        $snapshot = $status->snapshot();
        $snapshot['can_manage'] = $this->canManage($request);
        $snapshot['groups'] = $snapshot['can_manage'] ? rescue(fn () => $sync->groups(), [], false) : [];

        return response()->json($snapshot);
    }

    /** Sama dengan: php artisan dbsync:run [--dry-run] [--group=..] [--force] */
    public function run(Request $request, DbSyncService $sync): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'dry_run' => ['boolean'],
            'force' => ['boolean'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', Rule::in(array_keys($sync->groups()))],
        ]);

        // Sinkron pertama bisa beberapa menit; jangan berhenti bila browser ditutup.
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $result = $sync->run(
                $sync->tablesForGroups($data['groups'] ?? []),
                (bool) ($data['force'] ?? false),
                (bool) ($data['dry_run'] ?? false),
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result + ['dry_run' => (bool) ($data['dry_run'] ?? false)]);
    }

    /** Sama dengan: php artisan dbsync:resolve {table} {id} --keep=local|server|newest */
    public function resolve(Request $request, DbSyncService $sync): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'table' => ['required', 'string', Rule::in($sync->syncableTables())],
            'id' => ['required', 'string', 'max:64'],
            'keep' => ['required', Rule::in(['local', 'server', 'newest'])],
        ]);

        try {
            $stats = $sync->resolveConflict($data['table'], $data['id'], $data['keep']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($stats['failed'] || $stats['conflicts'] || $stats['deferred']) {
            return response()->json([
                'message' => $stats['deferred']
                    ? 'Baris sedang diubah saat ini, coba lagi.'
                    : implode(' ', array_merge($stats['errors'], $stats['warnings'])),
            ], 422);
        }

        return response()->json(['message' => "Baris {$data['table']} #{$data['id']} disamakan memakai versi {$data['keep']}."]);
    }

    /**
     * Selesaikan SEMUA konflik dari sinkron terakhir sekaligus memakai versi lokal / hosting
     * (bila modul dipilih: hanya konflik di tabel modul itu).
     */
    public function resolveAll(Request $request, DbSyncService $sync, DbSyncStatus $status): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'keep' => ['required', Rule::in(['local', 'server', 'newest'])],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', Rule::in(array_keys($sync->groups()))],
        ]);

        $rows = $status->conflictRows();
        if ($data['groups'] ?? []) {
            $tables = $sync->tablesForGroups($data['groups']);
            $rows = array_values(array_filter($rows, fn ($row) => in_array($row['table'], $tables, true)));
        }

        if (! $rows) {
            return response()->json(['message' => 'Tidak ada konflik untuk diselesaikan.', 'resolved' => [], 'failed' => []]);
        }

        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $result = $sync->resolveConflicts($rows, $data['keep']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $label = ['local' => 'LOKAL', 'server' => 'HOSTING', 'newest' => 'TERBARU'][$data['keep']];

        return response()->json($result + [
            'message' => count($result['resolved'])." konflik disamakan memakai versi $label"
                .($result['failed'] ? ', '.count($result['failed']).' gagal.' : '.'),
        ]);
    }

    /**
     * Perbedaan data satu baris (lokal vs hosting vs sinkron terakhir) untuk modal konflik di dashboard.
     * Server lokal membaca kedua database langsung; hosting tidak bisa menjangkau database lokal,
     * jadi memakai salinan yang disimpan sinkron terakhir (db_sync_conflicts).
     */
    public function conflict(Request $request, DbSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'table' => ['required', 'string', Rule::in($sync->syncableTables())],
            'id' => ['required', 'string', 'max:64'],
        ]);

        if (! DbSyncService::isConfigured()) {
            $row = rescue(fn () => $sync->storedConflict($data['table'], $data['id']), null, false);

            return $row
                ? response()->json($row + ['stored' => true])
                : response()->json(['message' => 'Data perbedaan baris ini tidak tersedia di hosting (konflik sudah diselesaikan, '
                    .'atau server lokal belum sinkron ulang). Buka dashboard di server lokal untuk melihat & menyelesaikannya.'], 404);
        }

        try {
            $row = $sync->tableSynchronizer($data['table'])->snapshot($data['id']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($row);
    }

    private function canManage(Request $request): bool
    {
        return DbSyncService::isConfigured()
            && in_array($request->user()?->role, self::MANAGER_ROLES, true);
    }
}
