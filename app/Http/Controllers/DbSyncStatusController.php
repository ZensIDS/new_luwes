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
        $snapshot['tables'] = $snapshot['can_manage'] ? rescue(fn () => $sync->syncableTables(), [], false) : [];

        return response()->json($snapshot);
    }

    /** Sama dengan: php artisan dbsync:run [--dry-run] [--table=..] [--force] */
    public function run(Request $request, DbSyncService $sync): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'dry_run' => ['boolean'],
            'force' => ['boolean'],
            'table' => ['nullable', 'string', Rule::in($sync->syncableTables())],
        ]);

        // Sinkron pertama bisa beberapa menit; jangan berhenti bila browser ditutup.
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $result = $sync->run(
                array_filter([$data['table'] ?? null]),
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

    private function canManage(Request $request): bool
    {
        return DbSyncService::isConfigured()
            && in_array($request->user()?->role, self::MANAGER_ROLES, true);
    }
}
