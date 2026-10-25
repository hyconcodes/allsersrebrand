<?php

use App\Models\Post;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->viewer = User::factory()->create();
    $this->author = User::factory()->create(['work' => 'Master Electrician And Solar Panel Installation Specialist']);

    $this->post = Post::create([
        'user_id' => $this->author->id,
        'content' => 'Fresh work from the workshop',
        'images' => 'hero.jpg,second.jpg',
    ]);

    $this->actingAs($this->viewer);
});

test('every post card image click stops propagation so only the card container navigates', function () {
    $html = Volt::test('dashboard.post-item', ['post' => $this->post])->html();

    $total = substr_count($html, "\$dispatch('open-lightbox'");
    $stopped = substr_count($html, '@click.stop="$dispatch(\'open-lightbox\'');

    expect($total)->toBeGreaterThan(0)
        ->and($stopped)->toBe($total);

    // The card itself still carries the navigation handler for non-image clicks
    expect($html)->toContain(
        "@click=\"window.location.href='".route('posts.show', $this->post)."'\""
    );
});

test('video containers also stop propagation so watching a video never navigates away', function () {
    $post = Post::create([
        'user_id' => $this->author->id,
        'content' => 'Video only post',
        'video' => 'clip.mp4',
    ]);

    $html = Volt::test('dashboard.post-item', ['post' => $post])->html();

    expect($html)->toContain('@click.stop class="relative');
});

test('the work badge is truncated to one line with a hover tooltip', function () {
    $work = $this->author->work;

    $html = Volt::test('dashboard.post-item', ['post' => $this->post])->html();

    expect($html)->toContain('title="'.$work.'"')
        ->toContain('truncate max-w-[160px] sm:max-w-[220px]');
});
