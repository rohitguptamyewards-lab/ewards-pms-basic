<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        DB::table('work_logs')->truncate();
        DB::table('project_blockers')->truncate();
        DB::table('project_transfers')->truncate();
        DB::table('project_ticket_links')->truncate();
        DB::table('project_updates')->truncate();
        DB::table('project_stages')->truncate();
        DB::table('project_workers')->truncate();
        DB::table('project_planners')->truncate();
        DB::table('projects')->truncate();
        DB::table('team_members')->truncate();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        DB::table('team_members')->insert([
            ['id' => 1, 'name' => 'Subhankar Manager', 'email' => 'manager@example.com', 'password' => Hash::make('password'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Ayashree Analyst', 'email' => 'ayashree@example.com', 'password' => Hash::make('password'), 'role' => 'analyst', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Mobashir Analyst', 'email' => 'mobashir@example.com', 'password' => Hash::make('password'), 'role' => 'analyst', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Rohit Developer', 'email' => 'rohit@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Sneha Developer', 'email' => 'sneha@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Soumyadip Developer', 'email' => 'soumyadip@example.com', 'password' => Hash::make('password'), 'role' => 'employee', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
