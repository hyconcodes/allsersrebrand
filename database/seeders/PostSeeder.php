<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlPath = base_path('ppzaydpb_allservices.sql');

        if (!file_exists($sqlPath)) {
            $this->command->error("SQL file not found at: $sqlPath");
            return;
        }

        $handle = fopen($sqlPath, "r");
        if ($handle) {
            $inPostsTable = false;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                // Detect start of posts insert block
                if (str_starts_with($line, 'INSERT INTO `posts`')) {
                    $inPostsTable = true;
                }

                // If we are in posts table but encountered a new INSERT for another table
                if ($inPostsTable && str_starts_with($line, 'INSERT INTO `') && !str_starts_with($line, 'INSERT INTO `posts`')) {
                    $inPostsTable = false;
                }

                // If line matches (int, ...) pattern
                if ($inPostsTable && str_starts_with($line, '(')) {
                    // It's a data row

                    // Remove leading '(' and trailing '),' or ');'
                    $trimLine = rtrim($line, ',;');
                    $trimLine = ltrim($trimLine, '(');
                    $trimLine = rtrim($trimLine, ')');

                    // Parse CSV respecting single quotes
                    $row = str_getcsv($trimLine, ',', "'", "\\");

                    if (count($row) < 7) {
                        continue;
                    }

                    // Indices:
                    // `post_id`, `content`, `image`, `video`, `user_id`, `date_created`, `time_created`
                    // 0: post_id, 1: content, 2: image, 3: video, 4: user_id, 5: date, 6: time

                    $content = $row[1] === 'NULL' ? '' : $row[1];

                    // Decode unicode escapes if any (some dumps have them), usually str_getcsv handles basic strings.
                    // But SQL dumps might strictly escape things differently. Content usually readable.

                    // Images: comma separated
                    $images = trim($row[2]);
                    if ($images !== 'NULL' && !empty($images)) {
                        $imageList = explode(',', $images);
                        $formattedImages = [];
                        foreach ($imageList as $img) {
                            $img = trim($img);
                            if (empty($img))
                                continue;

                            if (!str_starts_with($img, 'posts/images/')) {
                                $formattedImages[] = 'posts/images/' . $img;
                            } else {
                                $formattedImages[] = $img;
                            }
                        }
                        $images = !empty($formattedImages) ? implode(',', $formattedImages) : null;
                    } else {
                        $images = null;
                    }

                    // Video
                    $video = trim($row[3]);
                    if ($video !== 'NULL' && !empty($video)) {
                        if (!str_starts_with($video, 'posts/videos/')) {
                            $video = 'posts/videos/' . $video;
                        }
                    } else {
                        $video = null;
                    }

                    // Timestamps 
                    $date = $row[5] === 'NULL' ? date('Y-m-d') : $row[5];
                    $time = $row[6] === 'NULL' ? '00:00:00' : $row[6];
                    $createdAt = "$date $time";

                    $postData = [
                        'id' => (int) $row[0],
                        'user_id' => (int) $row[4],
                        'content' => $content,
                        'images' => $images,
                        'video' => $video,
                        // We will set timestamps manually on the model instance
                    ];

                    // Confirm user exists to avoid foreign key violation
                    if (!\App\Models\User::where('id', $postData['user_id'])->exists()) {
                        continue;
                    }

                    $post = \App\Models\Post::updateOrCreate(['id' => $postData['id']], $postData);

                    // Manually set timestamps
                    $post->created_at = $createdAt;
                    $post->updated_at = $createdAt; // Assuming updated same as created for seeded data
                    $post->save(['timestamps' => false]);
                }
            }
            fclose($handle);
        }
    }
}
