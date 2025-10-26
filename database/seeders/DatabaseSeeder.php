<?php

namespace Database\Seeders;

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
            // 1. Create admin user first
            UserSeeder::class,
            
            // 2. Create template families and templates
            TemplateSeeder::class,
            
            // 3. Create instructional template data examples
            InstructionalDataSeeder::class,
        ]);
    }
}
