<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'name' => 'Allsers Support',
            'email' => 'support@allsers.com',
            'username' => 'allsers_support',
            'password' => bcrypt('password'),
            'role' => 'artisan',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        \App\Models\User::create([
            'name' => 'Allsers Hello',
            'email' => 'hello@allsers.com',
            'username' => 'allsers_hello',
            'password' => bcrypt('password'),
            'role' => 'artisan',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
