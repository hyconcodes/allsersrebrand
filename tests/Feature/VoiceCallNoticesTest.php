<?php

use App\Mail\VoiceNoticeConsentMail;
use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Support\VoiceCallAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Configure fake Voicebip credentials and freeze time inside the
 * 08:00-20:00 Africa/Lagos call window (10:00 WAT on a weekday).
 */
function voicebipTestSetup(string $frozenAt = '2026-01-05 10:00:00', string $tz = 'Africa/Lagos'): void
{
    config()->set('services.voicebip', [
        'api_key' => 'pk_test_abc123',
        'agent_id' => 'agt_test_123',
        'from_number' => '+2341000001111',
        'webhook_secret' => 'whsec_test_secret',
        'endpoint' => 'https://api.voicebip.com/v1',
        'call_window_start' => 8,
        'call_window_end' => 20,
        'timezone' => 'Africa/Lagos',
    ]);

    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse($frozenAt, $tz));
}

function voicebipArtisan(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'artisan',
        'phone_number' => '08012345678',
        'country_code' => 'NG',
        'voice_call_consent' => 'accepted',
        'voice_call_language' => 'en',
    ], $attributes));
}

function voicebipEngagement(User $client, User $artisan): Engagement
{
    $conversation = \App\Models\Conversation::create(['last_message_at' => now()]);
    $conversation->users()->attach([$client->id, $artisan->id]);

    return Engagement::create([
        'user_id' => $client->id,
        'artisan_id' => $artisan->id,
        'conversation_id' => $conversation->id,
        'status' => 'pending',
        'title' => 'Fix my leaky kitchen sink faucet',
    ]);
}

afterEach(function () {
    \Illuminate\Support\Carbon::setTestNow();
});

test('phone numbers normalize to E.164 Nigerian format', function () {
    expect(VoiceCallAlert::normalizeToE164('08012345678'))->toBe('+2348012345678')
        ->and(VoiceCallAlert::normalizeToE164('+234 801 234 5678'))->toBe('+2348012345678')
        ->and(VoiceCallAlert::normalizeToE164('2348012345678'))->toBe('+2348012345678')
        ->and(VoiceCallAlert::normalizeToE164('7012345678'))->toBe('+2347012345678')
        ->and(VoiceCallAlert::normalizeToE164('555-0101'))->toBeNull()
        ->and(VoiceCallAlert::normalizeToE164(null))->toBeNull()
        ->and(VoiceCallAlert::normalizeToE164(''))->toBeNull();
});

test('call window only allows 08:00 to 20:00 Lagos time', function () {
    voicebipTestSetup('2026-01-05 07:59:00');
    expect(VoiceCallAlert::withinCallWindow())->toBeFalse();

    voicebipTestSetup('2026-01-05 08:00:00');
    expect(VoiceCallAlert::withinCallWindow())->toBeTrue();

    voicebipTestSetup('2026-01-05 19:59:59');
    expect(VoiceCallAlert::withinCallWindow())->toBeTrue();

    voicebipTestSetup('2026-01-05 20:00:01');
    expect(VoiceCallAlert::withinCallWindow())->toBeFalse();
});

test('accepted artisan is called when pinged and the announcement is stored', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_abc123', 'status' => 'initiated'], 201)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.voicebip.com/v1/calls'
            && $request['agent_id'] === 'agt_test_123'
            && $request['from_number'] === '+2341000001111'
            && $request['to_number'] === '+2348012345678';
    });

    $announcement = VoiceAnnouncement::where('user_id', $artisan->id)->sole();
    expect($announcement->status)->toBe('pending')
        ->and($announcement->call_id)->toBe('call_abc123')
        ->and($announcement->to_number)->toBe('+2348012345678')
        ->and($announcement->message)->toContain($client->name)
        ->and($announcement->message)->toContain('Fix my leaky kitchen sink faucet')
        ->and($announcement->message)->toContain('Log in to Allsers');
});

test('artisan without a consent decision or with a decline is never called', function () {
    voicebipTestSetup();
    Http::fake();

    $client = User::factory()->create();
    $engagementFixture = fn (User $artisan) => voicebipEngagement($client, $artisan);

    foreach ([null, 'declined', 'maybe'] as $consent) {
        $artisan = voicebipArtisan(['voice_call_consent' => $consent]);
        VoiceCallAlert::send($client, $artisan, $engagementFixture($artisan));
    }

    Http::assertNothingSent();
    expect(VoiceAnnouncement::count())->toBe(0);
});

test('calls are skipped outside the CBN call window', function () {
    voicebipTestSetup('2026-01-05 03:30:00');
    Http::fake();

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    Http::assertNothingSent();
    expect(VoiceAnnouncement::count())->toBe(0);
});

test('unusable phone numbers are skipped', function () {
    voicebipTestSetup();
    Http::fake();

    $client = User::factory()->create();
    $artisan = voicebipArtisan(['phone_number' => '555-0101']);
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    Http::assertNothingSent();
    expect(VoiceAnnouncement::count())->toBe(0);
});

test('a fresh pending announcement debounces repeat dials to the same number', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_debounce', 'status' => 'initiated'], 201)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();

    VoiceCallAlert::send($client, $artisan, voicebipEngagement($client, $artisan));
    VoiceCallAlert::send($client, $artisan, voicebipEngagement($client, $artisan));

    Http::assertSentCount(1);
    expect(VoiceAnnouncement::count())->toBe(1);
});

test('voice call api failures never break the send path', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response('nope', 500)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    $announcement = VoiceAnnouncement::sole();
    expect($announcement->status)->toBe('failed')
        ->and($announcement->call_id)->toBeNull();
});

test('unconfigured keys mean no call attempts at all', function () {
    config()->set('services.voicebip.api_key', null);
    Http::fake();

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Webhook endpoint
|--------------------------------------------------------------------------
*/

function voicebipSignedPost(array $event, array $overrides = []): \Illuminate\Testing\TestResponse
{
    $body = json_encode($event);
    $timestamp = $overrides['timestamp'] ?? (string) time();
    $secret = config('services.voicebip.webhook_secret');
    $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    if (isset($overrides['signature'])) {
        $signature = $overrides['signature'];
    }

    return test()->call('POST', '/webhooks/voicebip', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_VOICEBIP_SIGNATURE' => $signature,
        'HTTP_X_VOICEBIP_TIMESTAMP' => $timestamp,
    ], $body);
}

test('tool.invocation answers with the pending announcement and marks it spoken', function () {
    voicebipTestSetup();

    $artisan = voicebipArtisan();
    $announcement = VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348012345678',
        'message' => 'Kola has requested your services on Allsers. Log in to Allsers now to view the request and accept the job. Thank you.',
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = voicebipSignedPost([
        'event_type' => 'tool.invocation',
        'call_id' => 'call_abc123',
        'agent_id' => 'agt_test_123',
        'from' => '+2348012345678',
        'tool' => [
            'tool_call_id' => 'tc_1',
            'name' => 'get_ping_announcement',
            'arguments' => ['to_number' => '+2348012345678'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('message'))->toBe($announcement->message);

    $announcement->refresh();
    expect($announcement->status)->toBe('spoken')
        ->and($announcement->spoken_at)->not->toBeNull()
        ->and($announcement->call_id)->toBe('call_abc123');
});

test('unknown tools are rejected with an error result', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event_type' => 'tool.invocation',
        'call_id' => 'call_x',
        'tool' => ['tool_call_id' => 'tc_1', 'name' => 'track_order', 'arguments' => []],
    ]);

    $response->assertOk();
    expect($response->json('error'))->toBe('unknown_tool');
});

test('webhook rejects bad signatures and replayed timestamps', function () {
    voicebipTestSetup();

    $event = ['event_type' => 'call.completed', 'payload' => ['call_id' => 'call_x']];

    // Bit-flipped signature.
    voicebipSignedPost($event, ['signature' => 'sha256='.str_repeat('0', 64)])
        ->assertStatus(401);

    // Timestamp older than 5 minutes.
    voicebipSignedPost($event, ['timestamp' => (string) (time() - 400)])
        ->assertStatus(401);
});

test('call.completed fails an announcement that never got spoken', function () {
    voicebipTestSetup();

    $artisan = voicebipArtisan();
    $announcement = VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348012345678',
        'message' => 'Notice text.',
        'status' => 'pending',
        'call_id' => 'call_never_spoke',
        'expires_at' => now()->addMinutes(30),
    ]);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'from' => '+2348012345678',
        'payload' => ['call_id' => 'call_never_spoke', 'status' => 'completed', 'duration_seconds' => 12],
    ])->assertOk();

    expect($announcement->refresh()->status)->toBe('failed');
});

test('arbitrary events are acknowledged without side effects', function () {
    voicebipTestSetup();

    voicebipSignedPost([
        'event_type' => 'call.quality',
        'payload' => ['call_id' => 'call_x', 'quality_score' => 4.2],
    ])->assertOk()->assertExactJson(['received' => true]);
});

test('without a configured webhook secret deliveries are acknowledged', function () {
    voicebipTestSetup();
    config()->set('services.voicebip.webhook_secret', null);

    $this->call('POST', '/webhooks/voicebip', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['event_type' => 'call.completed', 'payload' => []]))
        ->assertOk()
        ->assertExactJson(['received' => true]);
});

/*
|--------------------------------------------------------------------------
| BYOM (Bring Your Own Model) turn contract
|--------------------------------------------------------------------------
*/

function byomAnnouncement(User $artisan, ?string $callId, string $to = '+2348147177291'): VoiceAnnouncement
{
    return VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => $to,
        'message' => 'Kola has just requested your services on Allsers. Log in to Allsers now and accept the job. Thank you.',
        'status' => 'pending',
        'call_id' => $callId,
        'expires_at' => now()->addMinutes(30),
    ]);
}

test('byom screening speaks the pending announcement and ends the call', function () {
    voicebipTestSetup();

    $artisan = voicebipArtisan();
    $announcement = byomAnnouncement($artisan, 'call_scr_1');

    $response = voicebipSignedPost([
        'event' => 'call.screening',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_scr_1',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'messages' => [],
    ]);

    $response->assertOk();
    expect($response->json('end_call'))->toBeTrue()
        ->and($response->json('text'))->toBe($announcement->message);

    expect($announcement->refresh()->status)->toBe('spoken')
        ->and($announcement->spoken_at)->not->toBeNull();
});

test('byom screening without a pending notice just allows the call', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event' => 'call.screening',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_scr_none',
        'from_number' => '+2348000000001',
        'to_number' => '+2341000001111',
        'messages' => [],
    ]);

    $response->assertOk();
    expect($response->json('end_call'))->toBeFalse()
        ->and($response->json('text'))->toBeNull();
});

test('byom turn delivers the announcement by number and hangs up', function () {
    voicebipTestSetup();

    $artisan = voicebipArtisan();
    $announcement = byomAnnouncement($artisan, null, '+2348147177291');

    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_1',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'transcription' => 'hello?',
        'messages' => [],
    ]);

    $response->assertOk();
    expect($response->json('text'))->toBe($announcement->message)
        ->and($response->json('end_call'))->toBeTrue();

    expect($announcement->refresh()->status)->toBe('spoken');
});

test('byom turn with no announcement speaks the generic notice and ends the call', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_none',
        'from_number' => '+2348000000002',
        'to_number' => '+2341000001111',
        'transcription' => 'yes?',
        'messages' => [],
    ]);

    $response->assertOk();
    expect($response->json('end_call'))->toBeTrue()
        ->and($response->json('text'))->toContain('automated notice from Allsers');
});

test('byom messaging payloads are acknowledged with an empty object', function () {
    voicebipTestSetup();

    voicebipSignedPost([
        'agent_id' => 'agt_test_123',
        'conversation_id' => 'conv_1',
        'channel' => 'whatsapp',
        'from_number' => '+2348000000003',
        'to_number' => '+2341000001111',
        'inbound_message' => ['message_id' => 'msg_1', 'body' => 'hi', 'type' => 'text'],
        'history' => [],
    ])->assertOk()->assertExactJson([]);
});

test('byom turns are rejected without a valid signature', function () {
    voicebipTestSetup();

    voicebipSignedPost([
        'event' => 'turn',
        'call_id' => 'call_x',
        'transcription' => 'hello',
    ], ['signature' => 'sha256='.str_repeat('0', 64)])
        ->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Consent settings flow
|--------------------------------------------------------------------------
*/

test('accepting voice call notices saves consent and sends the confirmation email', function () {
    Mail::fake();

    $artisan = voicebipArtisan(['voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('settings.profile')
        ->set('voiceLanguage', 'en')
        ->set('voiceConsent', 'accepted')
        ->call('saveVoiceCallConsent')
        ->assertHasNoErrors();

    $artisan->refresh();
    expect($artisan->voice_call_consent)->toBe('accepted')
        ->and($artisan->voice_call_language)->toBe('en')
        ->and($artisan->voice_call_consent_at)->not->toBeNull();

    Mail::assertSent(VoiceNoticeConsentMail::class, fn ($mail) => collect($mail->to)->pluck('address')->contains($artisan->email));
});

test('re-saving an existing acceptance does not re-send the confirmation email', function () {
    Mail::fake();

    $artisan = voicebipArtisan(['voice_call_consent' => 'accepted']);

    $this->actingAs($artisan);

    Volt::test('settings.profile')
        ->set('voiceConsent', 'accepted')
        ->call('saveVoiceCallConsent')
        ->assertHasNoErrors();

    Mail::assertNotSent(VoiceNoticeConsentMail::class);
});

test('declining saves silently with no email', function () {
    Mail::fake();

    $artisan = voicebipArtisan(['voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('settings.profile')
        ->set('voiceConsent', 'declined')
        ->call('saveVoiceCallConsent')
        ->assertHasNoErrors();

    expect($artisan->refresh()->voice_call_consent)->toBe('declined');
    Mail::assertNotSent(VoiceNoticeConsentMail::class);
});

test('saving without a decision is rejected', function () {
    $artisan = voicebipArtisan(['voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('settings.profile')
        ->set('voiceConsent', null)
        ->call('saveVoiceCallConsent')
        ->assertHasErrors(['voiceConsent' => 'required']);
});

test('only english can be selected while other languages are disabled', function () {
    $artisan = voicebipArtisan(['voice_call_consent' => null]);

    $this->actingAs($artisan);

    Volt::test('settings.profile')
        ->set('voiceLanguage', 'yo')
        ->set('voiceConsent', 'accepted')
        ->call('saveVoiceCallConsent')
        ->assertHasErrors(['voiceLanguage']);
});

test('guests cannot trigger the consent save', function () {
    $artisan = voicebipArtisan(['voice_call_consent' => null]);
    $guest = User::factory()->create(['role' => 'guest', 'phone_number' => '08099999999']);

    $this->actingAs($guest);

    Volt::test('settings.profile')
        ->set('voiceConsent', 'accepted')
        ->call('saveVoiceCallConsent');

    expect($guest->refresh()->voice_call_consent)->toBeNull();
});
