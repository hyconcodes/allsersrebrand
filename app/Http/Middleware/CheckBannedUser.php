<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isBanned()) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $bannedUntil = $user->banned_until->format('F j, Y g:i A');

                return redirect()->route('login')->with('error', "Your account has been suspended until {$bannedUntil}. Please contact support if you believe this is an error.");
            }
        }

        return $next($request);
    }
}
