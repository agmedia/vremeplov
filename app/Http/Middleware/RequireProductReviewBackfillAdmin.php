<?php

namespace App\Http\Middleware;

use App\Support\ProductReviewBackfillAccess;
use Closure;
use Illuminate\Http\Request;

class RequireProductReviewBackfillAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(ProductReviewBackfillAccess::allows($request->user()), 403);

        return $next($request);
    }
}
