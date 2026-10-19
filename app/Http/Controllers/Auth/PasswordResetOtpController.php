<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class PasswordResetOtpController extends Controller
{
    /**
     * Show the code-entry form for resetting a password.
     */
    public function create(Request $request)
    {
        return view('livewire.auth.reset-password-code', [
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Validate the emailed code and set the new password.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'max:16'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! $user->passwordResetCodeIsValid($request->string('code')->toString())) {
            return back()->withErrors([
                'code' => 'The reset code is invalid or has expired.',
            ])->withInput();
        }

        // Validates the password rules (min length etc.); throws back with
        // errors BEFORE the code is consumed, so a weak password keeps the
        // code usable.
        app(ResetsUserPasswords::class)->reset($user, $request->all());

        $user->clearPasswordResetCode();

        return redirect()->route('login')->with(
            'status',
            'Your password has been reset. You can now log in.'
        );
    }
}
