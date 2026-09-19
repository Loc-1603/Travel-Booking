<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip admin routes entirely — admin uses fixed Vietnamese
        if ($request->is('admin*') || $request->routeIs('admin.*')) {
            return $next($request);
        }

        $locale = PlatformSetting::get('locale', config('app.fallback_locale', 'en'));
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        return $next($request);
    }
}