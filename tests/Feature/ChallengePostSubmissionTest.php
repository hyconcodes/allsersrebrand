<?php

use App\Models\Challenge;
use App\Models\Post;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createActiveChallengeFor(User $creator, array $overrides = []): Challenge
{
    return Challenge::create(array_merge([
        'creator_id' => $creator->id,
        'title' => 'Community Craft Challenge',
        'hashtag' => 'CommunityCraft',
        'guidelines' => 'Create and share a handmade craft project with the community.',
        'prizes' => 'The winner receives a community badge.',
        'start_at' => now()->subHour(),
        'end_at' => now()->addDay(),
    ], $overrides));
}

test('challenge submission modal has a root element and opens for participants', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $challenge = createActiveChallengeFor($creator);
    $challenge->participants()->attach($participant->id, ['joined_at' => now()]);

    $this->actingAs($participant);

    $component = Volt::test('challenge.post-submission-modal')
        ->call('openModal', $challenge->id);

    $component->assertSet('showModal', true)
        ->assertSee($challenge->title);
});

test('challenge participants can submit a post with the challenge hashtag', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $challenge = createActiveChallengeFor($creator);
    $challenge->participants()->attach($participant->id, ['joined_at' => now()]);

    $this->actingAs($participant);

    Volt::test('challenge.post-submission-modal')
        ->call('openModal', $challenge->id)
        ->set('content', 'My handmade entry')
        ->call('submit')
        ->assertSet('showModal', false);

    expect(Post::query()
        ->where('user_id', $participant->id)
        ->where('challenge_id', $challenge->id)
        ->where('content', 'My handmade entry #CommunityCraft')
        ->exists())->toBeTrue();
});

test('participants can submit before the scheduled start time if the challenge has not ended', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $challenge = createActiveChallengeFor($creator, [
        'start_at' => now()->addHour(),
    ]);
    $challenge->participants()->attach($participant->id, ['joined_at' => now()]);

    $this->actingAs($participant);

    Volt::test('challenge.post-submission-modal')
        ->call('openModal', $challenge->id)
        ->assertSet('showModal', true)
        ->set('content', 'An early craft entry')
        ->call('submit')
        ->assertSet('showModal', false);

    expect(Post::query()
        ->where('user_id', $participant->id)
        ->where('challenge_id', $challenge->id)
        ->where('content', 'An early craft entry #CommunityCraft')
        ->exists())->toBeTrue();
});

test('near-you dashboard carousel shows challenges that have not ended and hides expired challenges', function () {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->sqliteCreateFunction('acos', fn ($value) => acos($value));
    $pdo->sqliteCreateFunction('cos', fn ($value) => cos($value));
    $pdo->sqliteCreateFunction('radians', fn ($value) => deg2rad($value));
    $pdo->sqliteCreateFunction('sin', fn ($value) => sin($value));

    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    createActiveChallengeFor($creator);
    createActiveChallengeFor($creator, [
        'title' => 'Expired Craft Challenge',
        'hashtag' => 'ExpiredCraft',
        'end_at' => now()->subMinute(),
    ]);

    $this->actingAs($viewer);

    Volt::test('dashboard.pros-widget', ['inFeed' => true, 'latitude' => null, 'longitude' => null])
        ->assertSee('Community Craft Challenge')
        ->assertDontSee('Expired Craft Challenge');
});
