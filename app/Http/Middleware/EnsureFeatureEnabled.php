<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\FeatureFlag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Block the request with a 404 when the given feature flag is disabled,
     * hiding the feature's existence entirely. Superadmin ghost users bypass
     * disabled flags.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $configKey): Response
    {
        $user = $request->user();

        abort_unless(FeatureFlag::enabled($configKey, $user instanceof User ? $user : null), 404);

        return $next($request);
    }
}
