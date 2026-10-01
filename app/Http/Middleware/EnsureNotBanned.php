<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_banned) {
            abort(403, 'This account has been banned.');
        }

        return $next($request);
    }
}
