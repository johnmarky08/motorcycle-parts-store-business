<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:admin,instructor'). Denies by default (OWASP A01) and logs refusals. */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            Log::channel('security')->warning('access.denied', [
                'user_id' => $user?->id,
                'role' => $user?->role,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            abort(403, 'You do not have access to this page.');
        }

        return $next($request);
    }
}
