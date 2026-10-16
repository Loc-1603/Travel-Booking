<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Gate a route cluster by staff role slug(s).
     *
     * Usage: ->middleware('role:vendor') or ->middleware('role:guide')
     * Multiple slugs allowed: ->middleware('role:vendor,guide').
     * The 'admin' slug covers both admin and super-admin.
     * Aborts with 403 when the authenticated user's role is not listed.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user || ! $this->roleMatchesAny($user->role, $roles)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function roleMatchesAny(?Role $role, array $roles): bool
    {
        foreach ($roles as $slug) {
            $allowed = match (strtolower(trim($slug))) {
                'admin' => [Role::ADMIN, Role::SUPER_ADMIN],
                'vendor' => [Role::VENDOR],
                'guide' => [Role::GUIDE],
                'customer' => [Role::CUSTOMER],
                default => [],
            };

            if (in_array($role, $allowed, true)) {
                return true;
            }
        }

        return false;
    }
}
