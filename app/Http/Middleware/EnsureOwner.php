<?php

namespace App\Http\Middleware;

use App\Support\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! Staff::canGovernInternal($user)) {
            abort(403);
        }

        return $next($request);
    }
}
