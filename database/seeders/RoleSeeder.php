<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin', // id = 1
            'Admin',       // id = 2
            'Cashier',     // id = 3
            'Customer',    // id = 4
            'Table',       // id = 5
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
