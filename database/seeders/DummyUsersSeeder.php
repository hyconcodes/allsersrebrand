<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domains = ['@yahoo.com', '@hotmail.com', '@outlook.com', '@aol.com', '@live.com', '@icloud.com'];

        \App\Models\User::factory()->count(30)->state(function (array $attributes) use ($domains) {
            $username = fake()->unique()->userName();
            // remove non-alphanumeric chars if any, just to ensure valid looking emails
            $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);

            // if username becomes empty after replacement, fallback to string
            if (empty($username)) {
                $username = \Illuminate\Support\Str::random(8);
            }

            $domain = fake()->randomElement($domains);

            return [
                'email' => strtolower($username.$domain),
            ];
        })->create();
    }
}
