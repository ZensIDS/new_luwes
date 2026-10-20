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

    /** Perbedaan data satu baris (lokal vs hosting vs sinkron terakhir) untuk modal konflik di dashboard. */
    public function conflict(Request $request, DbSyncService $sync): JsonResponse
    {
        abort_unless(DbSyncService::isConfigured(), 404);

        $data = $request->validate([
            'table' => ['required', 'string', Rule::in($sync->syncableTables())],
            'id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $row = $sync->inspectRow($data['table'], $data['id']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Nilai panjang (mis. JSON activity_log) dipotong supaya modal tetap ringan.
        $short = fn (?array $values) => $values === null ? null : array_map(
            fn ($v) => is_string($v) ? mb_strimwidth($v, 0, 2000, '…') : $v,
            $values
        );

        return response()->json([
            'local' => $short($row['local']),
            'server' => $short($row['server']),
            'base' => $short($row['base']),
        ] + $row);
    }

    private function canManage(Request $request): bool
    {
        return DbSyncService::isConfigured()
            && in_array($request->user()?->role, self::MANAGER_ROLES, true);
    }
}
