<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        if (RateLimiter::tooManyAttempts('register:'.request()->ip(), 3)) {
            RateLimiter::hit('register:'.request()->ip(), 3600);
            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. Please try again later.',
            ]);
        }
        RateLimiter::hit('register:'.request()->ip(), 3600);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class)],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'role' => ['required', 'string', Rule::in(['guest', 'artisan'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'password' => $this->passwordRules(),
        ];

        if (! empty(env('RECAPTCHA_SECRET_KEY'))) {
            $rules['g-recaptcha-response'] = ['required', new \App\Rules\RecaptchaV3];
        }

        Validator::make($input, $rules)->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'role' => $input['role'],
            'latitude' => $input['latitude'] ?? null,
            'longitude' => $input['longitude'] ?? null,
            'password' => $input['password'],
        ]);
    }
}
