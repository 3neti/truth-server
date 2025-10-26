<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the admin user.
     */
    public function run(): void
    {
        $this->command->info('Creating admin user...');

        User::firstOrCreate(
            ['email' => 'admin@disburse.cash'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Admin user created: admin@disburse.cash');
    }
}
