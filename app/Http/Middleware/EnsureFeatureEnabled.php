<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Block the request with a 404 when the given feature flag is disabled,
     * hiding the feature's existence entirely.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $configKey): Response
    {
        abort_unless((bool) config($configKey, true), 404);

        return $next($request);
    }
}
