<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Workbench\App\Models\Post;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Blockable used by the playground's curl examples in the README.
        Post::create(['title' => 'Bonjour depuis le playground']);
    }
}
