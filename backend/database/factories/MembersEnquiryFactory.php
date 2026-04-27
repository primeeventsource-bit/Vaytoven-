<?php

namespace Database\Factories;

use App\Models\MembersEnquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembersEnquiry>
 */
class MembersEnquiryFactory extends Factory
{
    protected $model = MembersEnquiry::class;

    public function definition(): array
    {
        return [
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'email'             => $this->faker->unique()->safeEmail(),
            'phone'             => $this->faker->phoneNumber(),
            'program'           => $this->faker->randomElement(MembersEnquiry::PROGRAMS),
            'property'          => $this->faker->company() . ', ' . $this->faker->city() . ' ' . $this->faker->stateAbbr(),
            'annual_points'     => $this->faker->numberBetween(50_000, 500_000),
            'best_time_to_call' => $this->faker->randomElement([
                'Morning (8am – 12pm)',
                'Afternoon (12pm – 5pm)',
                'Evening (5pm – 8pm)',
                'Weekends only',
                null,
            ]),
            'notes'             => $this->faker->optional(0.4)->paragraph(),
            'consent_given'     => true,
            'consent_at'        => now(),
            'source'            => $this->faker->randomElement(['website', 'app_search', 'app_host']),
            'ip_address'        => $this->faker->ipv4(),
            'user_agent'        => $this->faker->userAgent(),
            'status'            => 'new',
            'spam_score'        => 0,
            'flagged'           => false,
        ];
    }

    public function flagged(): self
    {
        return $this->state(fn () => [
            'flagged'    => true,
            'spam_score' => 80,
        ]);
    }

    public function status(string $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
