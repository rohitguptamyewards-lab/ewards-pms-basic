<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed if no team members exist (idempotent for production deploys)
        if (DB::table('team_members')->count() > 0) {
            return;
        }

        $members = [
            ['id' => 1, 'name' => 'Subhankar Manager', 'email' => 'manager@example.com', 'password' => Hash::make('password'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Ayashree Analyst', 'email' => 'ayashree@example.com', 'password' => Hash::make('password'), 'role' => 'analyst', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Mobashir Analyst', 'email' => 'mobashir@example.com', 'password' => Hash::make('password'), 'role' => 'analyst', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Rohit Developer', 'email' => 'rohit@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Sneha Developer', 'email' => 'sneha@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Soumyadip Developer', 'email' => 'soumyadip@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('team_members')->insert($members);
    }
}
