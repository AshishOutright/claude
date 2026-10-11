<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The builder is an internal tool: every page needs the BUILDER_PASSWORD login.
 * Without a password set, it only opens in the local environment.
 */
class RequireBuilderPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('builder.password')) {
            abort_unless(app()->environment('local', 'testing'), 503, 'Set BUILDER_PASSWORD in .env to open the SaaS Builder.');

            return $next($request);
        }

        if (! $request->session()->get('builder_authenticated')) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
