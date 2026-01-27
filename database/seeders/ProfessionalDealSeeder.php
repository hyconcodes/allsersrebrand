<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Engagement;
use App\Models\Review;
use App\Models\Post;
use Illuminate\Support\Str;

class ProfessionalDealSeeder extends Seeder
{
    public function run()
    {
        $guestId = 115;
        $artisanId = 21;

        $guest = User::find($guestId);
        $artisan = User::find($artisanId);

        if (!$guest || !$artisan) {
            $this->command->error("Users 115 or 21 not found.");
            return;
        }

        // 1. Create or Find Conversation
        $conversation = Conversation::whereHas('users', function ($q) use ($guestId) {
            $q->where('id', $guestId);
        })->whereHas('users', function ($q) use ($artisanId) {
            $q->where('id', $artisanId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create(['last_message_at' => now()]);
            $conversation->users()->attach([$guestId, $artisanId]);
        }

        // 2. Create Engagement
        $engagement = Engagement::create([
            'user_id' => $guestId,
            'artisan_id' => $artisanId,
            'conversation_id' => $conversation->id,
            'status' => 'completed',
            'is_public' => true,
            'title' => 'Web Dashboard Performance Optimization',
            'location_context' => 'Remote / Virtual',
            'urgency_level' => 'High',
            'price_estimate' => '₦150,000',
            'completion_estimate' => '5 Days',
            'showcase_description' => 'Successfully optimized a React-based CRM dashboard, reducing initial load time by 65% through code splitting, image optimization, and query caching. Also fixed 12 critical UI bugs reported by the client.',
            'showcase_photos' => [
                'before' => 'showcases/before/demo_broken_ui.png',
                'after' => 'showcases/after/demo_fixed_dashboard.png'
            ],
            'confirmed_at' => now()->subDays(6),
            'completed_at' => now()->subMinutes(30),
        ]);

        // 3. Create Review
        $review = Review::create([
            'reviewer_id' => $guestId,
            'artisan_id' => $artisanId,
            'engagement_id' => $engagement->id,
            'rating' => 5,
            'comment' => 'Incredible work! Olalekan is a true professional. He fixed all our dashboard performance issues and the code quality is top-notch. Highly recommended for any senior software needs.',
            'ip_address' => '127.0.0.1'
        ]);

        $engagement->update(['review_id' => $review->id]);

        // 4. Create Chat Messages (Workflow)
        $messages = [
            ['user_id' => $guestId, 'type' => 'inquiry', 'content' => "I'm interested in hiring you for: Web Dashboard Performance Optimization"],
            ['user_id' => $guestId, 'type' => 'text', 'content' => "Hi Olalekan, our current dashboard is very slow. Can you help us optimize it?"],
            ['user_id' => $artisanId, 'type' => 'text', 'content' => "Hi there! I absolutely can. I've worked on similar scaling issues before. Could you share the tech stack?"],
            ['user_id' => $guestId, 'type' => 'text', 'content' => "It's React with a Node back-end. I'll send over the docs."],
            ['user_id' => $artisanId, 'type' => 'quote', 'content' => "I've sent a quote: ₦150,000 (5 Days)"],
            ['user_id' => $guestId, 'type' => 'handshake', 'content' => "I've accepted your quote. Let's get started!"],
            ['user_id' => $artisanId, 'type' => 'text', 'content' => "Great! I'm starting now. I'll keep you updated daily."],
            ['user_id' => $artisanId, 'type' => 'text', 'content' => "Update: Code splitting implemented. Load time dropped by 2s already."],
            ['user_id' => $artisanId, 'type' => 'completion', 'content' => "Job marked as completed!"],
            ['user_id' => $guestId, 'type' => 'text', 'content' => "Everything looks perfect. Thank you so much!"]
        ];

        foreach ($messages as $msg) {
            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $msg['user_id'],
                'type' => $msg['type'],
                'engagement_id' => ($msg['type'] !== 'text') ? $engagement->id : null,
                'content' => $msg['content'],
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        // 5. Create Public Feed Post
        Post::create([
            'user_id' => $artisanId,
            'content' => "⭐ VERIFIED SUCCESS: Finished a major performance overhaul for a client's CRM. Reduced boot time from 8s to 1.5s! #performance #react #optimization",
            'images' => 'posts/demo_code_screenshot.png,posts/demo_lighthouse_score.png',
            'price_min' => 100000,
            'price_max' => 200000,
        ]);

        $this->command->info("Professional deal seeded successfully between User 115 and 21!");
    }
}
