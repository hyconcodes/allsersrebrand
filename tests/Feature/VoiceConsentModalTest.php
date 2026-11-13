<?php

use App\Mail\VoiceNoticeConsentMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the modal opens for artisans who have not made a choice yet', function () {
    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->assertSet('showModal', true)
        ->assertSee('Voice Call Notices')
        ->assertSee('Do you agree to receive voice call notices?');
});

test('the modal stays closed once a decision exists', function (string $consent) {
    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => $consent]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->assertSet('showModal', false)
        ->assertDontSee('Do you agree to receive voice call notices?');
})->with(['accepted', 'declined']);

test('guests and non-artisans never see the modal', function () {
    Volt::test('voice-consent-modal')
        ->assertSet('showModal', false)
        ->assertDontSee('Do you agree to receive voice call notices?');

    $client = User::factory()->create(['role' => 'client', 'voice_call_consent' => null]);

    $this->actingAs($client);

    Volt::test('voice-consent-modal')->assertSet('showModal', false);
});

test('accepting from the modal saves consent and mails the confirmation', function () {
    Mail::fake();

    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->set('voiceLanguage', 'en')
        ->set('voiceConsent', 'accepted')
        ->call('saveVoiceCallConsent')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $artisan->refresh();
    expect($artisan->voice_call_consent)->toBe('accepted')
        ->and($artisan->voice_call_language)->toBe('en')
        ->and($artisan->voice_call_consent_at)->not->toBeNull();

    Mail::assertSent(VoiceNoticeConsentMail::class, fn ($mail) => collect($mail->to)->pluck('address')->contains($artisan->email));
});

test('declining from the modal saves silently with no email', function () {
    Mail::fake();

    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->set('voiceConsent', 'declined')
        ->call('saveVoiceCallConsent')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect($artisan->refresh()->voice_call_consent)->toBe('declined');
    Mail::assertNotSent(VoiceNoticeConsentMail::class);
});

test('saving from the modal without choosing is rejected', function () {
    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->call('saveVoiceCallConsent')
        ->assertHasErrors(['voiceConsent']);

    expect($artisan->refresh()->voice_call_consent)->toBeNull();
});

test('the modal can be dismissed and will show again next visit', function () {
    $artisan = User::factory()->create(['role' => 'artisan', 'voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('voice-consent-modal')
        ->call('dismiss')
        ->assertSet('showModal', false);

    Volt::test('voice-consent-modal')->assertSet('showModal', true);
});
