<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AmenitiesSeeder::class,
            HelpArticleSeeder::class,
            LegalDocumentSeeder::class,
            PropertySeeder::class,
        ]);
    }
}
