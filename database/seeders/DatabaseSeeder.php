<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $adminRole = Role::where('name', 'Admin')->first();

        if ($adminRole) {
            User::firstOrCreate(
                ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
                [
                    'name' => env('ADMIN_NAME', 'Admin'),
                    'password' => env('ADMIN_PASSWORD', 'password'),
                    'role_id' => $adminRole->id,
                ]
            );
        }
    }
}
