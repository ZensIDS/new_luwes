<?php

namespace App\Support;

use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletAccess
{
    public static function id(Request $request, bool $required = true): ?int
    {
        $user = $request->user();
        $requested = $request->input('outlet_id');

        if ($user && in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless($user->outlet_id, 422, 'User belum memiliki outlet.');
            if ($requested && (int) $requested !== (int) $user->outlet_id) {
                abort(403, 'User tidak memiliki akses ke outlet tersebut.');
            }

            return (int) $user->outlet_id;
        }

        if ($requested) {
            abort_unless(Outlet::whereKey($requested)->exists(), 404, 'Outlet tidak ditemukan.');

            return (int) $requested;
        }

        if ($user?->outlet_id) {
            return (int) $user->outlet_id;
        }

        abort_if($required, 422, 'Outlet wajib dipilih.');

        return null;
    }

    public static function outlets()
    {
        $user = auth()->user();
        if ($user && in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            return Outlet::whereKey($user->outlet_id)->get();
        }

        return Outlet::orderBy('name')->get();
    }
}
