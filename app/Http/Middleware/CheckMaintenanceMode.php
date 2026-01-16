<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if maintenance mode is enabled in .env
        if (env('APP_MAINTENANCE_MODE', false)) {

            // Optional: Allow specific IP addresses or local environment bypass
            // if (in_array($request->ip(), ['127.0.0.1'])) {
            //     return $next($request);
            // }

            return response()->view('errors.maintenance', [], 503);
        }

        return $next($request);
    }
}
