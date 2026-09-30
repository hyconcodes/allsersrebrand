<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestSeeder extends Seeder
{
    /**
     * Seed two dummy guest users.
     */
    public function run(): void
    {
        $guests = [
            [
                'name' => 'Amara Guest',
                'username' => 'amara_guest',
                'email' => 'guest.amara@allsers.test',
            ],
            [
                'name' => 'Tunde Guest',
                'username' => 'tunde_guest',
                'email' => 'guest.tunde@allsers.test',
            ],
        ];

        foreach ($guests as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make('password'),
                    'role' => 'guest',
                    'email_verified_at' => now(),
                    'slug' => Str::slug($data['name']) . '-' . Str::random(8),
                ]
            );

            // Ensure slug exists for existing user without one (legacy)
            if (empty($user->slug)) {
                $user->update(['slug' => Str::slug($user->name) . '-' . Str::random(8)]);
            }

            $this->command?->info("Guest user ready: {$user->email} (role: {$user->role})");
        }
    }
}
