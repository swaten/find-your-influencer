<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // default admin login for local/dev - change the password after first login
        User::updateOrCreate(
            ['email' => 'admin@findyourinfluencer.local'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role_id' => Role::where('code', Role::ADMIN)->value('id'),
            ]
        );
    }
}
