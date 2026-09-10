<?php

namespace App\Http\Middleware;

use App\Support\CheckoutLoginRedirect;
use Closure;
use Illuminate\Http\Request;

class CaptureCheckoutLoginRedirect
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post') && $request->is('login') && ! $request->wantsJson()) {
            CheckoutLoginRedirect::capture($request);
        }

        return $next($request);
    }
}
