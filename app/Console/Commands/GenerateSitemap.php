<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate the sitemap for Allsers';

    public function handle()
    {
        $sitemap = Sitemap::create();

        // Add homepage
        $sitemap->add(Url::create('/')
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));

        // Add static pages
        $sitemap->add(Url::create('/privacy-policy')
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.5));

        $sitemap->add(Url::create('/terms-of-service')
            ->setLastModificationDate(now())
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setPriority(0.5));

        // Add artisan profiles (only verified artisans)
        User::where('role', 'artisan')
            ->whereNotNull('email_verified_at')
            ->chunk(100, function ($users) use ($sitemap) {
                foreach ($users as $user) {
                    $profileUrl = Url::create(route('artisan.profile', $user->slug))
                        ->setLastModificationDate($user->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8);

                    if ($user->profile_picture_url) {
                        $profileUrl->addImage($user->profile_picture_url, $user->name . ' - ' . ($user->work ?? 'Artisan') . ' on Allsers');
                    }

                    $sitemap->add($profileUrl);
                }
            });

        // Add public posts
        Post::with('user')
            ->latest()
            ->chunk(100, function ($posts) use ($sitemap) {
                foreach ($posts as $post) {
                    $sitemap->add(Url::create(route('posts.show', $post->post_id))
                        ->setLastModificationDate($post->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.6));
                }
            });

        // Add challenges
        \App\Models\Challenge::latest()->chunk(100, function ($challenges) use ($sitemap) {
            foreach ($challenges as $challenge) {
                $sitemap->add(Url::create(route('challenges.show', $challenge->custom_link))
                    ->setLastModificationDate($challenge->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.7));
            }
        });

        // Save sitemap
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');
    }
}
