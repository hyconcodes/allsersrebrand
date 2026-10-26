<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('accentColor returns the stored hex lowercased', function () {
    $user = User::factory()->create(['accent_color' => '#30A46C']);

    expect($user->accentColor())->toEqual('#30a46c');
});

test('accentColor falls back to a stable palette color when unset or invalid', function () {
    $user = User::factory()->create(['accent_color' => null]);

    $color = $user->accentColor();

    expect($color)->toMatch('/^#[0-9a-f]{6}$/')
        ->and(User::ACCENT_PALETTE)->toContain($color)
        ->and($color)->toEqual($user->fresh()->accentColor());

    $invalid = User::factory()->create(['accent_color' => 'not-a-color']);

    expect($invalid->accentColor())->toMatch('/^#[0-9a-f]{6}$/');
});

test('post thread renders author accent colors on root, comment and reply lines', function () {
    $author = User::factory()->create(['accent_color' => '#30a46c']);
    $commenter = User::factory()->create(['accent_color' => '#e5484d']);
    $replier = User::factory()->create(['accent_color' => '#3e63dd']);
    $viewer = User::factory()->create();

    $post = Post::create([
        'user_id' => $author->id,
        'content' => 'Thread with colored lines',
    ]);

    $comment = Comment::create([
        'user_id' => $commenter->id,
        'post_id' => $post->id,
        'content' => 'Top level comment',
    ]);

    Comment::create([
        'user_id' => $replier->id,
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'content' => 'Nested reply',
    ]);

    // A second top-level comment so the first one is not the last in the
    // thread (connectors are only drawn between comments).
    Comment::create([
        'user_id' => $author->id,
        'post_id' => $post->id,
        'content' => 'Another top level comment',
    ]);

    $this->actingAs($viewer);

    $html = Volt::test('pages.post-show', ['post' => $post])->html();

    // Root post connector carries the post author's color
    expect($html)->toContain('background-color: #30a46c');

    // Comment avatar connector carries the commenter's color
    expect($html)->toContain('background-color: #e5484d');

    // Reply branch border carries the parent comment author's color
    expect($html)->toContain('border-color: #e5484d');
});

test('feed post card renders author ring, trunk and inline comment elbow colors', function () {
    $author = User::factory()->create(['accent_color' => '#30a46c']);
    $commenter = User::factory()->create(['accent_color' => '#e5484d']);

    $post = Post::create([
        'user_id' => $author->id,
        'content' => 'Feed card with colored thread',
    ]);

    Comment::create([
        'user_id' => $commenter->id,
        'post_id' => $post->id,
        'content' => 'Inline comment',
    ]);

    $this->actingAs(User::factory()->create());

    $html = Volt::test('dashboard.post-item', ['post' => $post])->html();

    // Author avatar ring and root trunk line
    expect($html)->toContain('box-shadow: 0 0 0 2px #30a46c')
        ->toContain('background-color: #30a46c');

    // Comment avatar ring; elbow branches from the post author's trunk color
    expect($html)->toContain('box-shadow: 0 0 0 2px #e5484d')
        ->toContain('border-color: #30a46c');
});

test('profile settings save a custom accent color', function () {
    $this->actingAs($user = User::factory()->create());

    Volt::test('settings.profile')
        ->set('accent_color', '#abcdef')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->accent_color)->toEqual('#abcdef');
});

test('profile settings reject an invalid accent color', function () {
    $this->actingAs($user = User::factory()->create(['accent_color' => '#30a46c']));

    Volt::test('settings.profile')
        ->set('accent_color', 'oops')
        ->call('updateProfileInformation')
        ->assertHasErrors(['accent_color']);

    expect($user->fresh()->accent_color)->toEqual('#30a46c');
});
