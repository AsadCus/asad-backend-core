<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGhostAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Ghost is the lone bypass and stands purely on the ghost_users relation —
        // it must not depend on any role name (admin is just an editable role).
        abort_unless(
            $user !== null && $user->isGhostUser(),
            403,
            'You do not have the required role or permission to access this resource.'
        );

        return $next($request);
    }
}
