<?php

namespace ChinLeung\MultilingualRoutes;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectRequestLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->locale ?: $request->segment(1);

        if (in_array($segment, locales(), false)) {
            app()->setLocale($segment);
        }

        return $next($request);
    }
}
