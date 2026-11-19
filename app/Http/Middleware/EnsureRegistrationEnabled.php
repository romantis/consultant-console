<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRegistrationEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('console.allow_public_registration')) {
            abort(404); // or: return redirect()->route('login');
        }

        return $next($request);
    }
}
