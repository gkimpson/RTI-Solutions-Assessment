<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create admin user
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'role' => UserRole::Admin,
                'password' => Hash::make('password'),
            ]
        );

        // Create regular users
        User::query()->firstOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => 'Regular User 1',
                'role' => UserRole::User,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => 'Regular User 2',
                'role' => UserRole::User,
                'password' => Hash::make('password'),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user3@example.com'],
            [
                'name' => 'Regular User 3',
                'role' => UserRole::User,
                'password' => Hash::make('password'),
            ]
        );
    }
}
