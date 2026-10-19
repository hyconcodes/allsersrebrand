<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Re-enable the real "mailtrap" named mailer (TestCase swaps it to the
    // in-memory array transport so other tests never hit the network).
    config(['mail.mailers.mailtrap' => ['transport' => 'mailtrap']]);

    // Auth notifications follow the global mail driver (admin toggle), so put
    // the config in the state the settings page would: Mailtrap API active.
    config(['mail.default' => 'mailtrap']);

    config([
        'services.mailtrap.token' => 'test-mailtrap-token',
        'services.mailtrap.endpoint' => 'https://send.api.mailtrap.io/api/send',
        'services.mailtrap.from' => [
            'address' => 'hello@demomailtrap.co',
            'name' => 'Allsers',
        ],
    ]);

    // Intercept reCAPTCHA siteverify so registration passes offline.
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);
});

function fakeMailtrapSuccess(): void
{
    Http::fake([
        'send.api.mailtrap.io/api/send' => Http::response([
            'success' => true,
            'message_ids' => [1],
        ], 200),
    ]);
}

function fakeMailtrapFailure(): void
{
    Http::fake([
        'send.api.mailtrap.io/api/send' => Http::response([
            'errors' => [['message' => 'Unauthenticated']],
        ], 401),
    ]);
}

function registerViaUi(TestCase $test, string $email, string $username): void
{
    $response = $test->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => $username,
        'email' => $email,
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors();
}

test('verification OTP is sent via the Mailtrap API with the correct payload', function () {
    fakeMailtrapSuccess();

    registerViaUi($this, 'otp-ok@example.com', 'otpokuser');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://send.api.mailtrap.io/api/send'
            && $request->hasHeader('Authorization', 'Bearer test-mailtrap-token')
            && $request['from']['email'] === 'hello@demomailtrap.co'
            && $request['from']['name'] === 'Allsers'
            && $request['to'][0]['email'] === 'otp-ok@example.com'
            && str_contains($request['subject'], 'verification code')
            && str_contains($request['html'], 'Verification code')
            && $request['category'] === 'Auth';
    });
});

test('registration falls back to pending-verification-send-failed when the Mailtrap API rejects', function () {
    fakeMailtrapFailure();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'otpfailuser',
        'email' => 'otp-fail@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'registered-pending-verification-send-failed');

    $this->assertAuthenticated();
    expect(User::where('email', 'otp-fail@example.com')->exists())->toBeTrue();
});

test('password reset email is sent via the Mailtrap API', function () {
    fakeMailtrapSuccess();

    $user = User::factory()->create(['email' => 'reset-ok@example.com']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://send.api.mailtrap.io/api/send'
            && $request['to'][0]['email'] === 'reset-ok@example.com'
            && $request['category'] === 'Auth';
    });
});
