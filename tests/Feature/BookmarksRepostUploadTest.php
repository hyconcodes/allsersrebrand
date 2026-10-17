<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class BookmarksRepostUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_repost_video_dispatches_file_too_large_toast(): void
    {
        $this->actingAs(User::factory()->create());

        // 11MB: passes Livewire's temporary-upload cap (12MB) but exceeds
        // the component's max:10240 rule, hitting the try/catch toast path.
        $oversizedVideo = UploadedFile::fake()->create('clip.mp4', 11000, 'video/mp4');

        Livewire::test('dashboard.bookmarks-feed')
            ->set('repostVideo', $oversizedVideo)
            ->call('createRepost')
            ->assertHasErrors(['repostVideo'])
            ->assertDispatched('toast', type: 'warning', title: 'File Too Large');
    }

    public function test_oversized_repost_image_dispatches_file_too_large_toast(): void
    {
        $this->actingAs(User::factory()->create());

        $oversizedImage = UploadedFile::fake()->create('photo.jpg', 11000, 'image/jpeg');

        Livewire::test('dashboard.bookmarks-feed')
            ->set('repostImage', $oversizedImage)
            ->call('createRepost')
            ->assertHasErrors(['repostImage'])
            ->assertDispatched('toast', type: 'warning', title: 'File Too Large');
    }
}
