<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view for the given portal.
     * $portal is injected from the route defaults (admin|vendor|guide).
     */
    public function create(Request $request, string $portal = 'vendor'): View
    {
        $portal = $this->normalizePortal($portal);

        return view('auth.login', ['portal' => $portal]);
    }

    /**
     * Handle an incoming authentication request.
     * $portal is injected from the route defaults (admin|vendor|guide).
     */
    public function store(LoginRequest $request, string $portal = 'vendor'): RedirectResponse
    {
        $portal = $this->normalizePortal($portal);

        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if (! $this->roleMatchesPortal($user->role, $portal)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('auth.login.wrong_portal', [
                    'portal' => $portal,
                    'url' => route($user->portalLoginRoute()),
                ]),
            ]);
        }

        return redirect()->intended($user->dashboardRoute());
    }

    /**
     * Normalize the portal slug (admin|vendor|guide).
     */
    protected function normalizePortal(string $portal): string
    {
        return in_array($portal, ['admin', 'vendor', 'guide'], true) ? $portal : 'vendor';
    }

    protected function roleMatchesPortal(?Role $role, string $portal): bool
    {
        return match ($portal) {
            'admin' => in_array($role, [Role::SUPER_ADMIN, Role::ADMIN], true),
            'vendor' => $role === Role::VENDOR,
            'guide' => $role === Role::GUIDE,
            default => false,
        };
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
