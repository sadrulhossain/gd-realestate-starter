<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The rental module is optional: with RENTALS_ENABLED=false its routes answer 404. */
class EnsureRentalsEnabled
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('rentals.enabled'), 404);

        return $next($request);
    }
}
