<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create sample tags
        $tags = [
            ['name' => 'urgent', 'color' => '#ff4444'],
            ['name' => 'feature', 'color' => '#4444ff'],
            ['name' => 'bug', 'color' => '#ff8844'],
            ['name' => 'enhancement', 'color' => '#44ff44'],
            ['name' => 'documentation', 'color' => '#ffff44'],
            ['name' => 'testing', 'color' => '#ff44ff'],
            ['name' => 'refactor', 'color' => '#44ffff'],
            ['name' => 'performance', 'color' => '#8844ff'],
            ['name' => 'security', 'color' => '#ff0000'],
            ['name' => 'ui/ux', 'color' => '#ff8800'],
        ];

        foreach ($tags as $tagData) {
            Tag::firstOrCreate(
                ['name' => $tagData['name']],
                $tagData
            );
        }
    }
}
