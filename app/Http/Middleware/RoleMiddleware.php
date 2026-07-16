<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();

        // 1. Flatten roles from middleware parameter
        $allowedRoles = collect($roles)->flatMap(fn ($role) => explode('|', $role))->toArray();

        // 2. Grant access if role matches
        if (in_array($user->role, $allowedRoles)) {
            return $next($request);
        }

        // 3. Define landing pages per role
        $homeRoutes = [
            'customer'     => 'market.index',
            'admin-gudang' => 'dashboard',
            'staff-outlet' => 'dashboard',
            'owner'        => 'dashboard',
            'superadmin'   => 'dashboard',
        ];

        $target = $homeRoutes[$user->role] ?? 'login';

        // 4. Loop Prevention: If already on target route but still failing middleware
        if ($request->routeIs($target)) {
            abort(403, 'Unauthorized access to role home.');
        }

        return redirect()->route($target)->with('toast_error', 'Akses ditolak.');
    }
}
