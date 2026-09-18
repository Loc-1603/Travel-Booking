<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorApproved
{
    /**
     * Block vendor-only hotel-cluster routes until the vendor is approved.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->role === Role::VENDOR && ! Auth::user()->isVendorApproved()) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return response()->view('admin.vendor.pending-approval', [], 200);
            }

            return redirect()->route('admin.vendor.dashboard')
                ->with('error', __('admin.vendor.pending_approval_feature'));
        }

        return $next($request);
    }
}