<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $student = Role::firstOrCreate(['name' => 'student']);
        $class = StudentClass::firstOrCreate(['code' => 'WEB-A'], ['name' => 'Web Development A', 'status' => 'active']);
        User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'role_id' => $admin->id, 'password' => 'password', 'status' => 'active']);
        User::firstOrCreate(['email' => 'student@example.com'], ['name' => 'Student', 'role_id' => $student->id, 'class_id' => $class->id, 'password' => 'password', 'status' => 'active']);
    }
}
