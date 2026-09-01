<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove any existing headers from hosting provider / server config
        // that might block the Notification API
        $response->headers->remove('Permissions-Policy');
        $response->headers->remove('Feature-Policy');

        // Set our own that explicitly allows notifications + geolocation
        $response->headers->set(
            'Permissions-Policy',
            'notification=(self), microphone=(), camera=(), geolocation=(self)'
        );

        $response->headers->set(
            'Feature-Policy',
            "notification 'self'"
        );

        return $response;
    }
}
