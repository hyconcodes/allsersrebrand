<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateUserRolesAndUsernamesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Change all 'member' role to 'artisan'
        $updatedRoles = DB::table('users')
            ->where('role', 'member')
            ->update(['role' => 'artisan']);

        $this->command->info("Updated $updatedRoles users from 'member' to 'artisan' role.");

        // Remove the '@' at the beginning of all username
        // Using MySQL specific TRIM(LEADING ... ) or PHP loop if DB independent
        // Assuming MySQL based on previous context

        $users = DB::table('users')
            ->where('username', 'LIKE', '@%')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $newUsername = ltrim($user->username, '@');

            // Ensure uniqueness could be tricky if 'username' and '@username' both existed, 
            // but we assume they don't or validation isn't strict here.

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $newUsername]);
            $count++;
        }

        $this->command->info("Removed leading '@' from $count usernames.");
    }
}
