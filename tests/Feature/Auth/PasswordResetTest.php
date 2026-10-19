<?php

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('reset password request screen can be rendered', function () {
    $this->get(route('password.request'))->assertStatus(200);
});

test('password reset code can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status', 'We’ve emailed a password reset code to that address if it exists in our system.');

    Notification::assertSentTo($user, PasswordResetCodeNotification::class);

    $user->refresh();

    expect($user->password_reset_code)->not->toBeNull()
        ->and($user->password_reset_expires_at->isFuture())->toBeTrue();
});

test('code entry screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, PasswordResetCodeNotification::class, function () use ($user) {
        $this->get(route('password.code', ['email' => $user->email]))
            ->assertStatus(200);

        return true;
    });
});

test('password can be reset with a valid code', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    $code = $user->fresh()->password_reset_code;

    $this->post(route('password.code.update'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login', absolute: false));

    $user->refresh();

    expect(Hash::check('brand-new-password', $user->password))->toBeTrue()
        ->and($user->password_reset_code)->toBeNull()
        ->and($user->password_reset_expires_at)->toBeNull();
});

test('password cannot be reset with an invalid code', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    $this->post(route('password.code.update'), [
        'email' => $user->email,
        'code' => 'NOTTHE1',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('code');

    $user->refresh();

    expect(Hash::check('brand-new-password', $user->password))->toBeFalse()
        ->and($user->password_reset_code)->not->toBeNull();
});

test('an expired reset code is rejected', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    $user->forceFill(['password_reset_expires_at' => now()->subMinute()])->save();

    $this->post(route('password.code.update'), [
        'email' => $user->email,
        'code' => $user->fresh()->password_reset_code,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeFalse();
});

test('forgot-password reports an honest failure message when the mailer is down', function () {
    Mail::shouldReceive('mailer')->andThrow(new TransportException('Connection refused'));

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email])
        ->assertSessionHas('status', 'We couldn’t send the reset code right now. Please try again shortly.');

    // The code is still generated so the user can retry immediately.
    expect($user->fresh()->password_reset_code)->not->toBeNull();
});
