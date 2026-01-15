<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
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
            $inUsersTable = false;
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                // Detect start of users insert block
                if (str_starts_with($line, 'INSERT INTO `users`')) {
                    $inUsersTable = true;
                    // continue to next line? No, it might be a single line insert or multi-line. 
                    // Usually multi-line. Let's assume standard dump format.
                    // But wait, the line itself might contain values.
                    // If it ends with 'VALUES', next lines are values.
                }

                // If we are in users table but encountered a new INSERT for another table
                if ($inUsersTable && str_starts_with($line, 'INSERT INTO `') && !str_starts_with($line, 'INSERT INTO `users`')) {
                    $inUsersTable = false;
                }

                // If line matches (int, ...) pattern
                if ($inUsersTable && str_starts_with($line, '(')) {
                    // It's a data row: (val, val, ...), or (val, val, ...);

                    // Remove leading '(' and trailing '),' or ');'
                    $trimLine = rtrim($line, ',;');
                    $trimLine = ltrim($trimLine, '(');
                    $trimLine = rtrim($trimLine, ')');

                    // Parse CSV respecting single quotes
                    // We use str_getcsv with single quote as enclosure. Escape char is backslash.
                    $row = str_getcsv($trimLine, ',', "'", "\\");

                    if (count($row) < 16) {
                        continue; // invalid row or parsing error
                    }

                    // Indices based on:
                    // `user_id`, `username`, `email`, `password`, `latitude`, `longitude`, `lastname`, `firstname`, `profile_picture`, `gender`, `work`, `bio`, `experience_year`, `work_status`, `phone_number`, `address`, ...
                    // 0: id, 1: username, 2: email, 3: password, 4: lat, 5: long, 6: lastname, 7: firstname, 8: pic, 9: gender, 10: work, 11: bio, 12: exp, 13: status, 14: phone, 15: address

                    $work = isset($row[10]) ? $row[10] : 'Guest';

                    // Role inference
                    $role = 'artisan';
                    if (strpos(strtolower($work), 'guest') !== false) {
                        $role = 'member';
                    }

                    // Name composition
                    $firstName = $row[7] ?? '';
                    $lastName = $row[6] ?? '';
                    $name = trim("$firstName $lastName");
                    if (empty($name)) {
                        $name = $row[1] ?? 'User'; // Fallback to username
                    }
                    // Handle "NULL" string from SQL dump vs null value
                    if ($firstName === 'NULL')
                        $name = trim("$lastName");
                    if ($lastName === 'NULL')
                        $name = trim("$firstName");
                    if ($name === 'NULL' || $name === '')
                        $name = $row[1];

                    // Profile Pic
                    $profilePic = $row[8] ?? 'no_pic.jpg';
                    if ($profilePic === 'NULL' || is_null($profilePic)) {
                        $profilePic = 'no_pic.jpg'; // Default
                    }
                    // Avoid double prefix if running multiple times (though raw dump won't have it)
                    if (!str_starts_with($profilePic, 'profilePics/')) {
                        $profilePic = 'profilePics/' . $profilePic;
                    }

                    $userData = [
                        'id' => (int) $row[0],
                        'username' => $row[1],
                        'name' => $name,
                        'email' => $row[2], // email
                        'password' => $row[3], // hashed password from dump
                        'latitude' => ($row[4] === 'NULL') ? null : (float) $row[4],
                        'longitude' => ($row[5] === 'NULL') ? null : (float) $row[5],
                        'profile_picture' => $profilePic,
                        'gender' => $row[9] ?? 'Male', // Default or from dump
                        'work' => $work === 'NULL' ? 'Guest' : $work,
                        'bio' => ($row[11] === 'NULL') ? null : $row[11],
                        'experience_year' => ($row[12] === 'NULL') ? null : $row[12],
                        'work_status' => ($row[13] === 'NULL') ? null : $row[13],
                        'phone_number' => ($row[14] === 'NULL') ? null : $row[14],
                        'address' => ($row[15] === 'NULL') ? null : $row[15],
                        'role' => $role,
                    ];

                    // Ensure username is unique if it differs from the one associated with the email
                    $existingUser = \App\Models\User::where('username', $userData['username'])->first();
                    if ($existingUser && $existingUser->email !== $userData['email']) {
                        $userData['username'] = $userData['username'] . '_' . uniqid();
                    }

                    \App\Models\User::updateOrCreate(['email' => $userData['email']], $userData);
                }
            }
            fclose($handle);
        }
    }
}
