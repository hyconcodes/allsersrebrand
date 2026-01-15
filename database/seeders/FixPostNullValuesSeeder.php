<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixPostNullValuesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fix 'posts/images/ NULL' and similar variations in images column
        $updatedImages = DB::table('posts')
            ->where('images', 'LIKE', '%posts/images/%NULL%')
            ->update(['images' => null]);

        $this->command->info("Fixed $updatedImages posts with invalid images value.");

        // Fix 'posts/videos/ NULL' and similar variations in video column
        $updatedVideos = DB::table('posts')
            ->where('video', 'LIKE', '%posts/videos/%NULL%')
            ->update(['video' => null]);

        $this->command->info("Fixed $updatedVideos posts with invalid video value.");
    }
}
