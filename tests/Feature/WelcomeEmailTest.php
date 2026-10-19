<?php

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests receive the guest welcome email after verifying their email', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create(['role' => 'guest']);
    $code = $user->generateEmailVerificationCode();

    $this->actingAs($user)
        ->post('/email/verify', ['email' => $user->email, 'code' => $code])
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) use ($user) {
        $html = $mail->render();

        return $mail->hasTo($user->email)
            && str_contains($html, 'Lilia from Allsers.')
            && str_contains($html, 'Find Artisans')
            && ! str_contains($html, 'Complete your business information');
    });
});

test('artisans receive the artisan welcome email with a business info prompt', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create(['role' => 'artisan']);
    $code = $user->generateEmailVerificationCode();

    $this->actingAs($user)
        ->post('/email/verify', ['email' => $user->email, 'code' => $code])
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) use ($user) {
        $html = $mail->render();

        return $mail->hasTo($user->email)
            && str_contains($html, 'Lilia from Allsers.')
            && str_contains($html, 'Welcome, artisan!')
            && str_contains($html, 'Complete your business information');
    });
});

test('artisans with a complete business profile are not prompted to update it', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create([
        'role' => 'artisan',
        'profile_picture' => 'profile-pictures/example.jpg',
        'gender' => 'male',
        'work' => 'electrician',
        'bio' => 'An experienced electrician.',
        'experience_year' => 5,
        'work_status' => 'available',
        'phone_number' => '08012345678',
        'address' => '12 Example Street',
    ]);

    $code = $user->generateEmailVerificationCode();

    $this->actingAs($user)
        ->post('/email/verify', ['email' => $user->email, 'code' => $code]);

    Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) {
        $html = $mail->render();

        return ! str_contains($html, 'Complete your business information')
            && str_contains($html, 'Lilia from Allsers.');
    });
});

test('verification still succeeds when the welcome email cannot be sent', function () {
    Mail::shouldReceive('mailer')->andThrow(new TransportException('Connection refused'));

    $user = User::factory()->unverified()->create(['role' => 'guest']);
    $code = $user->generateEmailVerificationCode();

    $this->actingAs($user)
        ->post('/email/verify', ['email' => $user->email, 'code' => $code])
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
