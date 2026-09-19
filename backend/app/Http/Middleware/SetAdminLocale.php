<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always set Vietnamese for admin routes
        app()->setLocale('vi');
        
        // Also set Carbon locale for date formatting
        \Carbon\Carbon::setLocale('vi');
        
        // Also set in session to persist across requests
        Session::put('locale', 'vi');
        
        return $next($request);
    }
}