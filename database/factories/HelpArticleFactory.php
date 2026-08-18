<?php

namespace Database\Factories;

use App\Enums\HelpAudience;
use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    protected $model = HelpArticle::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(5), '.');

        return [
            'slug'            => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 100000),
            'audience'        => $this->faker->randomElement(HelpAudience::cases()),
            'category'        => $this->faker->randomElement(['Getting started', 'Listings', 'Billing', 'Account']),
            'title'           => $title,
            'summary'         => $this->faker->sentence(),
            'body'            => $this->faker->paragraphs(3, true),
            'search_keywords' => $this->faker->words(4, true),
            'is_published'    => true,
            'sort_order'      => $this->faker->numberBetween(0, 50),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
