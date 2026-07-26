<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\Factory as HttpClient;

class RecaptchaV3 implements ValidationRule
{
    protected HttpClient $http;
    protected string $secret;

    public function __construct(?HttpClient $http = null, ?string $secret = null)
    {
        $this->http = $http ?? app(HttpClient::class);
        $this->secret = $secret ?? config('services.recaptcha.secret') ?? '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = $this->http->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $this->secret,
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if (! $response->successful() || ! $response->json('success') || $response->json('score') < 0.5) {
            $fail('The reCAPTCHA verification failed. Are you a bot?');
        }
    }
}
