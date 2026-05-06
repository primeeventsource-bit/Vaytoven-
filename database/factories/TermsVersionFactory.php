<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TermsVersionFactory extends Factory
{
    public function definition(): array
    {
        $content = fake()->paragraphs(3, true);

        return [
            'kind' => 'tos',
            'version_label' => '2026-05-06',
            'content_hash' => hash('sha256', $content),
            'content_url' => 'https://vaytoven.com/legal/tos',
            'effective_at' => now(),
            'created_at' => now(),
        ];
    }
}
