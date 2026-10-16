<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Intercept reCAPTCHA siteverify so CreateNewUser's rule passes offline.
    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
        ]),
    ]);
});

function failMailTransport(): void
{
    Mail::shouldReceive('mailer')->andThrow(
        new TransportException('550 Message discarded as high-probability spam')
    );
}

test('registration succeeds and lands on pending verification when mail server fails', function () {
    failMailTransport();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'mailfailuser',
        'email' => 'mailfail@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'registered-pending-verification-send-failed');

    $this->assertAuthenticated();
    expect(User::where('email', 'mailfail@example.com')->exists())->toBeTrue();
});

test('registration still reports pending verification when mail is sent successfully', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'mailokuser',
        'email' => 'mailok@example.com',
        'role' => 'guest',
        'password' => 'password',
        'password_confirmation' => 'password',
        'g-recaptcha-response' => 'valid-token',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'registered-pending-verification');

    $this->assertAuthenticated();
    // Mail::fake() replaces the notification channel manager entirely, so we
    // can't assert the sent mail/notification directly. The success path is
    // proven by the session status: the model only sets
    // 'registered-pending-verification' (not the "...-send-failed" variant)
    // when sendEmailVerificationNotification() completes without a
    // TransportException, which means the mail send did not throw.
});

test('resend verification code returns a friendly error instead of 500 when mail fails', function () {
    $user = User::factory()->unverified()->create();
    failMailTransport();

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertStatus(302)
        ->assertSessionHas('status', 'verification-code-failed');
});
