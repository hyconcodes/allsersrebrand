<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertStatus(200)
        ->assertSee('Verify your email')
        ->assertSee('Enter the 8-character verification code');
});

test('email can be verified with a valid OTP', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $code = $user->generateEmailVerificationCode();

    $response = $this->actingAs($user)->post('/email/verify', [
        'email' => $user->email,
        'code' => $code,
    ]);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with an invalid OTP', function () {
    $user = User::factory()->unverified()->create();

    $user->generateEmailVerificationCode();

    $this->actingAs($user)->post('/email/verify', [
        'email' => $user->email,
        'code' => 'INVALID99',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user submitting OTP is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $code = $user->generateEmailVerificationCode();

    $this->actingAs($user)->post('/email/verify', [
        'email' => $user->email,
        'code' => $code,
    ])
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});