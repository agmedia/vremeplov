<?php

namespace App\Http\Middleware;

use Bouncer;
use Closure;
use Illuminate\Http\Request;

class RequireAdministrator
{
    /**
     * Restrict sensitive business and system operations to store managers.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $isAdministrator = $user && (
            Bouncer::is($user)->an('master')
            || Bouncer::is($user)->an('admin')
        );

        abort_unless($isAdministrator, 403);

        return $next($request);
    }
}
