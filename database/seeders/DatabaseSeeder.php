<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            AmenitiesSeeder::class,
            HelpArticleSeeder::class,
            LegalDocumentSeeder::class,
            ExchangeNetworksSeeder::class,
            PropertySeeder::class,
        ]);
    }
}
