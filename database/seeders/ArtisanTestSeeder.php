<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ArtisanTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseLat = 6.4474;
        $baseLng = 3.3903;

        // 10 Fashion Designers
        for ($i = 1; $i <= 10; $i++) {
            User::updateOrCreate(
                ['email' => "fashion{$i}@example.com"],
                [
                    'name' => "Fashion Designer {$i}",
                    'username' => "fashion_pro_{$i}",
                    'password' => Hash::make('password'),
                    'role' => 'artisan',
                    'work' => 'Fashion Design',
                    'latitude' => $baseLat + (rand(-100, 100) / 10000),
                    'longitude' => $baseLng + (rand(-100, 100) / 10000),
                    'address' => "Street {$i}, Lagos",
                    'experience_year' => rand(1, 15),
                    'status' => 'active',
                ]
            );
        }

        // 10 Random Works
        $works = ['Carpentry', 'Graphic Design', 'Makeup Artist', 'Barber', 'Mechanic', 'Painter', 'Tutor', 'Cleaner', 'Photographer', 'Welder'];
        foreach ($works as $index => $work) {
            $i = $index + 1;
            User::updateOrCreate(
                ['email' => "artisan_random{$i}@example.com"],
                [
                    'name' => "{$work} Expert {$i}",
                    'username' => strtolower(str_replace(' ', '_', $work)) . "_{$i}",
                    'password' => Hash::make('password'),
                    'role' => 'artisan',
                    'work' => $work,
                    'latitude' => $baseLat + (rand(-150, 150) / 10000),
                    'longitude' => $baseLng + (rand(-150, 150) / 10000),
                    'address' => "Location {$i}, Lagos",
                    'experience_year' => rand(2, 20),
                    'status' => 'active',
                ]
            );
        }
    }
}
