<?php

namespace App\Http\Middleware;

use Bouncer;
use Closure;
use Illuminate\Http\Request;

class RequireBoxNowManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $isManager = $user && (
            Bouncer::is($user)->an('master')
            || Bouncer::is($user)->an('admin')
        );

        abort_unless($isManager, 403);

        return $next($request);
    }
}
