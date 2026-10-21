<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\UserTagged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserTaggedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentioned_user_is_notified_when_artisan_creates_post(): void
    {
        Notification::fake();

        $author = User::factory()->create(['role' => 'artisan', 'username' => 'tag_author']);
        $mentioned = User::factory()->create(['username' => 'mentioned_user']);

        $this->actingAs($author);

        Livewire::test('dashboard.create-post-modal')
            ->set('content', 'Check this out @mentioned_user @tag_author')
            ->call('createPost')
            ->assertHasNoErrors();

        $post = Post::where('user_id', $author->id)->firstOrFail();

        Notification::assertSentTo(
            $mentioned,
            UserTagged::class,
            function (UserTagged $notification) use ($post, $author) {
                return $notification->post->id === $post->id
                    && $notification->tagger->id === $author->id;
            }
        );

        // Authors must never notify themselves when they mention their own handle.
        Notification::assertNotSentTo($author, UserTagged::class);
    }
}
