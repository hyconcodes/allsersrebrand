<?php

use App\Models\Post;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->viewer = User::factory()->create();
    $this->author = User::factory()->create();

    foreach (range(1, 15) as $i) {
        Post::create([
            'user_id' => $this->author->id,
            'content' => "Post number {$i}",
            'created_at' => now()->subMinutes(16 - $i),
            'updated_at' => now()->subMinutes(16 - $i),
        ]);
    }

    $this->actingAs($this->viewer);
});

test('liking a post keeps the posts loaded via Show more', function () {
    $feed = Volt::test('dashboard.feed');

    expect($feed->get('posts'))->toHaveCount(10)
        ->and($feed->get('page'))->toBe(1);

    $feed->call('loadMore');

    expect($feed->get('posts'))->toHaveCount(15)
        ->and($feed->get('page'))->toBe(2);

    $feed->dispatch('post-liked');

    expect($feed->get('posts'))->toHaveCount(15)
        ->and($feed->get('page'))->toBe(2);
});

test('bookmarks and comments sync in place without collapsing loaded pages', function () {
    $feed = Volt::test('dashboard.feed')->call('loadMore');

    $target = collect($feed->get('posts'))->first();
    $target->bookmarks()->create(['user_id' => $this->viewer->id]);

    $feed->dispatch('post-bookmarked');

    $refreshed = collect($feed->get('posts'))->firstWhere('id', $target->id);

    expect(data_get($refreshed, 'bookmarks'))->toHaveCount(1)
        ->and($feed->get('posts'))->toHaveCount(15)
        ->and($feed->get('page'))->toBe(2);

    $feed->dispatch('comment-added');

    expect($feed->get('posts'))->toHaveCount(15)
        ->and($feed->get('page'))->toBe(2);
});

test('like counts on already-loaded posts still refresh after the event', function () {
    $feed = Volt::test('dashboard.feed');

    $target = collect($feed->get('posts'))->first();

    $target->likes()->create(['user_id' => User::factory()->create()->id]);

    $feed->dispatch('post-liked');

    $refreshed = collect($feed->get('posts'))->firstWhere('id', $target->id);

    expect(data_get($refreshed, 'likes_count'))->toBe(1)
        ->and($feed->get('posts'))->toHaveCount(10);
});

test('deleting a loaded post drops it without resetting pagination', function () {
    $feed = Volt::test('dashboard.feed')->call('loadMore');

    expect($feed->get('posts'))->toHaveCount(15);

    $victimId = collect($feed->get('posts'))->last()->id;
    Post::findOrFail($victimId)->delete();

    $feed->dispatch('post-deleted');

    expect($feed->get('posts'))->toHaveCount(14)
        ->and($feed->get('page'))->toBe(2);
});

test('a newly created post still resets the feed to the top', function () {
    $feed = Volt::test('dashboard.feed')->call('loadMore');

    expect($feed->get('posts'))->toHaveCount(15);

    $new = Post::create([
        'user_id' => $this->author->id,
        'content' => 'Brand new post from the modal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $feed->dispatch('post-created');

    $posts = collect($feed->get('posts'));

    expect($posts)->toHaveCount(10)
        ->and($feed->get('page'))->toBe(1)
        ->and($posts->first()->id)->toBe($new->id);
});
