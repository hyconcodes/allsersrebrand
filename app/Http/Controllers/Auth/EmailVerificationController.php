<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return redirect()->route('dashboard', ['verified' => 1]);
        }

        return view('livewire.auth.verify-email');
    }

    public function send(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard', ['verified' => 1]);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'max:16'],
        ]);

        $user = $request->user();

        if (! $user || $user->email !== $request->email) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard', ['verified' => 1]);
        }

        if (! $user->verifyEmailOtp($request->code)) {
            return back()->withErrors([
                'code' => 'The verification code is invalid or has expired.',
            ])->withInput();
        }

        return redirect()->route('dashboard', ['verified' => 1]);
    }
}
