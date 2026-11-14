<?php

use App\Mail\VoiceNoticeConsentMail;
use App\Models\Engagement;
use App\Models\User;
use App\Models\VoiceAnnouncement;
use App\Notifications\PingAvailability;
use App\Services\VoicebipClient;
use App\Support\VoiceCallAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

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

/**
 * A dialed notice row mid-chain: attempt N placed, awaiting an outcome
 * (answered / missed / due callback). Overrides inject chain states.
 */
function voicebipChainAnnouncement(User $artisan, array $overrides = []): VoiceAnnouncement
{
    return VoiceAnnouncement::create(array_merge([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348012345678',
        'message' => VoiceCallAlert::NOTICE,
        'status' => VoiceAnnouncement::STATUS_SPOKEN,
        'spoken_at' => now(),
        'call_id' => 'call_chain_1',
        'attempt' => 1,
        'expires_at' => now()->addMinutes(30),
    ], $overrides));
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
    expect($announcement->status)->toBe('spoken')
        ->and($announcement->spoken_at)->not->toBeNull()
        ->and($announcement->call_id)->toBe('call_abc123')
        ->and($announcement->to_number)->toBe('+2348012345678')
        ->and($announcement->message)->toContain($client->name)
        ->and($announcement->message)->toContain('Allsers.com')
        ->and($announcement->message)->toContain('Log in to Allsers.com');

    // Greeting PATCH + dial.
    Http::assertSentCount(2);
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

    Http::assertSentCount(2); // greeting PATCH + dial; second send debounced
    expect(VoiceAnnouncement::count())->toBe(1);
});

test('the dynamic notice is patched into the greeting before dialing', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_dynamic', 'status' => 'initiated'], 201)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    $recorded = Http::recorded();
    expect($recorded)->toHaveCount(2);

    $greeting = $recorded[0][0];
    $dial = $recorded[1][0];

    expect($greeting->method())->toBe('PATCH')
        ->and($greeting->url())->toContain('/agents/agt_test_123')
        ->and($greeting['greeting_text'])->toContain($client->name)
        ->and($greeting['greeting_text'])->toContain('Fix my leaky kitchen sink faucet')
        ->and($greeting['greeting_text'])->toContain('Allsers.com')
        ->and($dial->method())->toBe('POST')
        ->and($dial->url())->toContain('/calls');
});

test('the greeting patch also applies the 2s idle timeout and the silent idle prompt', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_notice_cfg', 'status' => 'initiated'], 201)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    VoiceCallAlert::send($client, $artisan, voicebipEngagement($client, $artisan));

    $recorded = Http::recorded();
    $greeting = $recorded[0][0];

    expect($greeting->method())->toBe('PATCH')
        ->and($greeting['idle_timeout_seconds'])->toBe(2)
        ->and($greeting['idle_prompt'])->toBe('.');
});

test('dials carry a max_duration hard cap so the default 20s cap cannot clip a long notice', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_capped', 'status' => 'initiated'], 201)]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    VoiceCallAlert::send($client, $artisan, voicebipEngagement($client, $artisan));

    $recorded = Http::recorded();
    $dial = $recorded[1][0];

    expect($dial->method())->toBe('POST')
        ->and($dial->url())->toContain('/calls')
        ->and($dial['max_duration'])->toBe(60);
});

test('a failed greeting patch never dials', function () {
    voicebipTestSetup();
    Http::fake([
        'https://api.voicebip.com/v1/agents/agt_test_123' => Http::response('nope', 500),
        'https://api.voicebip.com/v1/calls' => Http::response(['call_id' => 'call_never', 'status' => 'initiated'], 201),
    ]);

    $client = User::factory()->create();
    $artisan = voicebipArtisan();
    $engagement = voicebipEngagement($client, $artisan);

    VoiceCallAlert::send($client, $artisan, $engagement);

    $announcement = VoiceAnnouncement::sole();
    expect($announcement->status)->toBe('failed')
        ->and($announcement->call_id)->toBeNull();

    Http::assertSentCount(1); // PATCH only — the dial never fired
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

test('endCall hangs up through the official DELETE route', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['status' => 'completed'], 200)]);

    expect(VoicebipClient::endCall('call_x'))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.voicebip.com/v1/calls/call_x');
});

test('endCall retries a flaky failure', function () {
    voicebipTestSetup();
    Http::fake([
        'https://api.voicebip.com/v1/calls/call_y' => Http::sequence()
            ->push('temporary', 500)
            ->push(['status' => 'completed'], 200),
    ]);

    expect(VoicebipClient::endCall('call_y'))->toBeTrue();
    Http::assertSentCount(2);
});

test('endCall verifies a JSON 404 against the real call state before believing it', function () {
    voicebipTestSetup();

    Http::fake(function ($request) {
        if ($request->method() === 'DELETE') {
            return Http::response(
                ['error_code' => 'NotFound', 'message' => 'call: not found'],
                404,
                ['Content-Type' => 'application/json'],
            );
        }

        return Http::response(['call_id' => 'call_z', 'status' => 'completed'], 200);
    });

    expect(VoicebipClient::endCall('call_z'))->toBeTrue();
    Http::assertSentCount(2); // DELETE -> 404, GET -> completed
});

test('endCall retries when a JSON 404 is a phantom and the call is still alive', function () {
    voicebipTestSetup();

    Http::fake(function ($request) {
        if ($request->method() === 'DELETE') {
            return Http::response(
                ['error_code' => 'NotFound', 'message' => 'call: not found'],
                404,
                ['Content-Type' => 'application/json'],
            );
        }

        return Http::response(['call_id' => 'call_alive', 'status' => 'initiated'], 200);
    });

    expect(VoicebipClient::endCall('call_alive'))->toBeFalse();
    Http::assertSentCount(6); // 3 attempts x (DELETE + verifying GET)
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
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348147177291',
        'message' => 'Notice text.',
        'status' => 'pending',
        'call_id' => 'call_never_spoke',
        'expires_at' => now()->addMinutes(30),
    ]);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'from' => '+2348147177291',
        'payload' => ['call_id' => 'call_never_spoke', 'status' => 'completed', 'duration_seconds' => 2],
    ])->assertOk();

    expect($announcement->refresh()->status)->toBe('failed');
    Notification::assertNothingSent();
});

test('call.completed settles a pending announcement as spoken when the duration shows it played', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348147177291',
        'message' => 'Notice text.',
        'status' => 'pending',
        'call_id' => 'call_did_speak',
        'expires_at' => now()->addMinutes(30),
    ]);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'from' => '+2348147177291',
        'payload' => ['call_id' => 'call_did_speak', 'status' => 'completed', 'duration_seconds' => 14],
    ])->assertOk();

    $announcement->refresh();
    expect($announcement->status)->toBe('spoken')
        ->and($announcement->spoken_at)->not->toBeNull()
        ->and($announcement->answered_at)->not->toBeNull();

    Notification::assertSentTo(
        $announcement->engagement->user,
        PingAvailability::class,
        fn (PingAvailability $notification) => $notification->mode === PingAvailability::AVAILABLE,
    );
});

test('call.completed downgrades a dial-marked announcement when the call was never answered', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348147177291',
        'message' => 'Notice text.',
        'status' => 'spoken',
        'spoken_at' => now(),
        'call_id' => 'call_never_answered',
        'expires_at' => now()->addMinutes(30),
    ]);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'from' => '+2348147177291',
        'payload' => ['call_id' => 'call_never_answered', 'status' => 'completed', 'duration_seconds' => 2],
    ])->assertOk();

    $announcement->refresh();
    expect($announcement->status)->toBe('failed')
        ->and($announcement->spoken_at)->toBeNull()
        ->and($announcement->next_attempt_at)->not->toBeNull();

    Notification::assertNothingSent();
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

test('byom screening always allows the call through to the static greeting', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event' => 'call.screening',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_scr_1',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'messages' => [],
    ]);

    $response->assertOk()->assertExactJson(['end_call' => false]);
});

test('byom turn stays silent while the greeting plays so noise cannot cut or repeat it', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_1',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'transcription' => 'hello?',
        'messages' => [],
    ]);

    $response->assertOk()->assertExactJson(['text' => '', 'end_call' => false]);
});

test('byom turn stays silent even when the supervisor flagged the greeting as done', function () {
    voicebipTestSetup();

    \Illuminate\Support\Facades\Cache::put('voicebip:greeted:call_turn_2', true, 600);

    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_2',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'transcription' => 'yes?',
        'messages' => [],
    ]);

    $response->assertOk()->assertExactJson(['text' => '', 'end_call' => false]);
});

test('byom turn stays silent even when the greeting timestamp shows it finished long ago', function () {
    voicebipTestSetup();

    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_stale',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'transcription' => 'hello?',
        'messages' => [
            [
                'role' => 'agent',
                'text' => VoiceCallAlert::NOTICE,
                'timestamp' => now()->subSeconds(30)->toISOString(),
            ],
            ['role' => 'caller', 'text' => 'hello?', 'timestamp' => now()->toISOString()],
        ],
    ]);

    $response->assertOk()->assertExactJson(['text' => '', 'end_call' => false]);
});

test('byom turn stays silent while the greeting is still being spoken', function () {
    voicebipTestSetup();

    // Greeting started 5s ago — still playing; do not cut it.
    $response = voicebipSignedPost([
        'event' => 'turn',
        'agent_id' => 'agt_test_123',
        'call_id' => 'call_turn_early',
        'from_number' => '+2348147177291',
        'to_number' => '+2341000001111',
        'transcription' => 'hello?',
        'messages' => [
            [
                'role' => 'agent',
                'text' => VoiceCallAlert::NOTICE,
                // Voicebip-style nanosecond precision.
                'timestamp' => now()->subSeconds(5)->format('Y-m-d\TH:i:s.uuuuuuuuu\Z'),
            ],
        ],
    ]);

    $response->assertOk()->assertExactJson(['text' => '', 'end_call' => false]);
});

test('idle silence hangs up our notice calls from the webhook once the greeting played', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['status' => 'completed'], 200)]);

    $artisan = voicebipArtisan();
    VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348147177291',
        'message' => VoiceCallAlert::NOTICE,
        'status' => 'spoken',
        'spoken_at' => now(),
        'call_id' => 'call_idle_notice',
        'expires_at' => now()->addMinutes(30),
    ]);

    \Illuminate\Support\Facades\Cache::put('voicebip:greeted:call_idle_notice', true, 600);

    voicebipSignedPost([
        'event_type' => 'call.idle_silence',
        'payload' => ['call_id' => 'call_idle_notice', 'from_number' => '+2348147177291'],
    ])->assertOk()->assertExactJson(['received' => true]);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.voicebip.com/v1/calls/call_idle_notice');
});

test('idle silence before the greeting frame never kills the notice', function () {
    voicebipTestSetup();
    Http::fake();

    $artisan = voicebipArtisan();
    VoiceAnnouncement::create([
        'user_id' => $artisan->id,
        'engagement_id' => voicebipEngagement(User::factory()->create(), $artisan)->id,
        'to_number' => '+2348147177291',
        'message' => VoiceCallAlert::NOTICE,
        'status' => 'spoken',
        'spoken_at' => now(),
        'call_id' => 'call_pre_greeting',
        'expires_at' => now()->addMinutes(30),
    ]);

    voicebipSignedPost([
        'event_type' => 'call.idle_silence',
        'payload' => ['call_id' => 'call_pre_greeting', 'from_number' => '+2348147177291'],
    ])->assertOk()->assertExactJson(['received' => true]);

    Http::assertNothingSent();
});

test('idle silence on calls that are not ours does nothing', function () {
    voicebipTestSetup();
    Http::fake();

    voicebipSignedPost([
        'event_type' => 'call.idle_silence',
        'payload' => ['call_id' => 'call_someone_elses', 'from_number' => '+2348000000009'],
    ])->assertOk()->assertExactJson(['received' => true]);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Ping availability chain (answer detection / 3-min callbacks / outcomes)
|--------------------------------------------------------------------------
*/

test('a connected call tells the pinger the artisan is available and cancels callbacks', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = voicebipChainAnnouncement($artisan, ['next_attempt_at' => now()->addMinutes(2)]);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'payload' => ['call_id' => 'call_chain_1', 'status' => 'completed', 'duration_seconds' => 14],
    ])->assertOk();

    $announcement->refresh();
    expect($announcement->answered_at)->not->toBeNull()
        ->and($announcement->next_attempt_at)->toBeNull()
        ->and($announcement->status)->toBe('spoken');

    Notification::assertSentTo(
        $announcement->engagement->user,
        PingAvailability::class,
        fn (PingAvailability $notification) => $notification->mode === PingAvailability::AVAILABLE,
    );
});

test('the available notification fires exactly once under duplicate webhook deliveries', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = voicebipChainAnnouncement($artisan);

    $delivery = [
        'event_type' => 'call.completed',
        'payload' => ['call_id' => 'call_chain_1', 'status' => 'completed', 'duration_seconds' => 14],
    ];

    voicebipSignedPost($delivery)->assertOk();
    voicebipSignedPost($delivery)->assertOk();

    Notification::assertSentToTimes($announcement->engagement->user, PingAvailability::class, 1);
});

test('a missed call schedules the next callback in 3 minutes without notifying yet', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = voicebipChainAnnouncement($artisan);

    voicebipSignedPost([
        'event_type' => 'call.completed',
        'payload' => ['call_id' => 'call_chain_1', 'status' => 'completed', 'duration_seconds' => 2],
    ])->assertOk();

    $announcement->refresh();
    expect($announcement->answered_at)->toBeNull()
        ->and($announcement->pinger_notified)->toBeNull()
        ->and($announcement->status)->toBe('failed')
        // Wall-clock comparison: test time is frozen in Africa/Lagos while
        // the app timezone is UTC, so round-tripped datetimes must be
        // compared as formatted local strings, not as instants.
        ->and($announcement->next_attempt_at->format('Y-m-d H:i:s'))->toBe(now()->addMinutes(3)->format('Y-m-d H:i:s'));

    Notification::assertNothingSent();
});

test('a no_answer terminal event also advances the callback chain', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = voicebipChainAnnouncement($artisan);

    voicebipSignedPost([
        'event_type' => 'call.no_answer',
        'payload' => ['call_id' => 'call_chain_1', 'status' => 'no_answer'],
    ])->assertOk();

    $announcement->refresh();
    expect($announcement->status)->toBe('failed')
        ->and($announcement->next_attempt_at)->not->toBeNull();

    Notification::assertNothingSent();
});

test('the third missed call tells the pinger the artisan is unavailable exactly once', function () {
    voicebipTestSetup();
    Notification::fake();

    $artisan = voicebipArtisan();
    $announcement = voicebipChainAnnouncement($artisan, ['attempt' => 3]);

    $delivery = [
        'event_type' => 'call.completed',
        'payload' => ['call_id' => 'call_chain_1', 'status' => 'completed', 'duration_seconds' => 1],
    ];

    voicebipSignedPost($delivery)->assertOk();
    voicebipSignedPost($delivery)->assertOk();

    $announcement->refresh();
    expect($announcement->pinger_notified)->toBe(PingAvailability::UNAVAILABLE)
        ->and($announcement->pinger_notified_at)->not->toBeNull()
        ->and($announcement->next_attempt_at)->toBeNull()
        ->and($announcement->status)->toBe('failed');

    Notification::assertSentToTimes($announcement->engagement->user, PingAvailability::class, 1);
    Notification::assertSentTo(
        $announcement->engagement->user,
        PingAvailability::class,
        fn (PingAvailability $notification) => $notification->mode === PingAvailability::UNAVAILABLE,
    );
});

test('the scheduler dials a due callback and bumps the attempt', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_cb_2', 'status' => 'initiated'], 201)]);
    Notification::fake();

    $due = voicebipChainAnnouncement(voicebipArtisan(), [
        'call_id' => 'call_cb_1',
        'next_attempt_at' => now()->subMinute(),
    ]);

    $this->artisan('voice:dispatch-retries')->assertSuccessful();

    Http::assertSentCount(2); // greeting PATCH + callback dial
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://api.voicebip.com/v1/calls');

    $due->refresh();
    expect($due->attempt)->toBe(2)
        ->and($due->call_id)->toBe('call_cb_2')
        ->and($due->next_attempt_at)->toBeNull()
        ->and($due->status)->toBe('spoken');
});

test('the scheduler skips answered and not-yet-due chains', function () {
    voicebipTestSetup();
    Http::fake();
    Notification::fake();

    $artisan = voicebipArtisan();
    $answered = voicebipChainAnnouncement($artisan, [
        'answered_at' => now(),
        'pinger_notified' => PingAvailability::AVAILABLE,
        'next_attempt_at' => now()->subMinute(),
    ]);
    $waiting = voicebipChainAnnouncement($artisan, ['next_attempt_at' => now()->addMinutes(2)]);

    $this->artisan('voice:dispatch-retries')->assertSuccessful();

    Http::assertNothingSent();
    expect($answered->refresh()->attempt)->toBe(1)
        ->and($waiting->refresh()->attempt)->toBe(1);
});

test('a live callback chain debounces a new ping to the same artisan', function () {
    voicebipTestSetup();
    Http::fake(['*' => Http::response(['call_id' => 'call_new_ping', 'status' => 'initiated'], 201)]);
    Notification::fake();

    $client = User::factory()->create();
    $artisan = voicebipArtisan();

    voicebipChainAnnouncement($artisan, [
        'status' => VoiceAnnouncement::STATUS_FAILED,
        'next_attempt_at' => now()->addMinutes(2),
    ]);

    VoiceCallAlert::send($client, $artisan, voicebipEngagement($client, $artisan));

    Http::assertNothingSent();
    expect(VoiceAnnouncement::count())->toBe(1);
});

test('a callback that would fall outside the call window ends the chain with unavailable', function () {
    voicebipTestSetup('2026-01-05 20:30:00');
    Http::fake();
    Notification::fake();

    $due = voicebipChainAnnouncement(voicebipArtisan(), ['next_attempt_at' => now()->subMinute()]);

    $this->artisan('voice:dispatch-retries')->assertSuccessful();

    Http::assertNothingSent();

    $due->refresh();
    expect($due->pinger_notified)->toBe(PingAvailability::UNAVAILABLE)
        ->and($due->next_attempt_at)->toBeNull();

    Notification::assertSentTo(
        $due->engagement->user,
        PingAvailability::class,
        fn (PingAvailability $notification) => $notification->mode === PingAvailability::UNAVAILABLE,
    );
});

test('consent withdrawn mid-chain ends the callback without dialing', function () {
    voicebipTestSetup();
    Http::fake();
    Notification::fake();

    $due = voicebipChainAnnouncement(voicebipArtisan(['voice_call_consent' => 'declined']), [
        'next_attempt_at' => now()->subMinute(),
    ]);

    $this->artisan('voice:dispatch-retries')->assertSuccessful();

    Http::assertNothingSent();
    expect($due->refresh()->pinger_notified)->toBe(PingAvailability::UNAVAILABLE);
});

test('expired unresolved chains notify unavailable, but only recent dialed ones', function () {
    voicebipTestSetup();
    Http::fake();
    Notification::fake();

    $freshPinger = User::factory()->create();
    $oldPinger = User::factory()->create();
    $undialedPinger = User::factory()->create();

    $expired = voicebipChainAnnouncement(voicebipArtisan(), [
        'engagement_id' => voicebipEngagement($freshPinger, voicebipArtisan())->id,
        'expires_at' => now()->subMinutes(10),
    ]);
    $old = voicebipChainAnnouncement(voicebipArtisan(), [
        'engagement_id' => voicebipEngagement($oldPinger, voicebipArtisan())->id,
        'expires_at' => now()->subDays(3),
    ]);
    $undialed = voicebipChainAnnouncement(voicebipArtisan(), [
        'engagement_id' => voicebipEngagement($undialedPinger, voicebipArtisan())->id,
        'expires_at' => now()->subMinutes(5),
        'call_id' => null,
        'attempt' => 0,
        'status' => VoiceAnnouncement::STATUS_FAILED,
    ]);

    $this->artisan('voice:dispatch-retries')->assertSuccessful();

    Http::assertNothingSent();

    expect($expired->refresh()->pinger_notified)->toBe(PingAvailability::UNAVAILABLE)
        ->and($old->refresh()->pinger_notified)->toBeNull()
        ->and($undialed->refresh()->pinger_notified)->toBeNull();

    Notification::assertSentToTimes($expired->engagement->user, PingAvailability::class, 1);
    Notification::assertNotSentTo($old->engagement->user, PingAvailability::class);
    Notification::assertNotSentTo($undialed->engagement->user, PingAvailability::class);
});

test('the ping availability notification reaches in-app, push and email with the right copy', function () {
    voicebipTestSetup();

    $artisan = voicebipArtisan(['name' => 'Chidi the Plumber']);
    $pinger = User::factory()->create();
    $engagement = voicebipEngagement($pinger, $artisan);

    $available = new PingAvailability($artisan, $engagement, PingAvailability::AVAILABLE);

    expect($available->via($pinger))->toBe(['database', 'mail', WebPushChannel::class])
        ->and($available->toMail($pinger)->subject)->toContain('Chidi the Plumber is available')
        ->and($available->toArray($pinger)['mode'])->toBe('available')
        ->and($available->toArray($pinger)['url'])->toContain('/chat')
        ->and($available->toWebPush($pinger, $available))->toBeInstanceOf(\NotificationChannels\WebPush\WebPushMessage::class);

    $unavailable = new PingAvailability($artisan, $engagement, PingAvailability::UNAVAILABLE);

    expect($unavailable->toMail($pinger)->subject)->toContain('did not answer')
        ->and($unavailable->toArray($pinger)['message'])->toContain('3 call attempts');
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
