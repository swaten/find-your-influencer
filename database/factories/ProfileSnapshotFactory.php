<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $followers = fake()->numberBetween(100, 5_000_000);

        return [
            'profile_id' => Profile::factory(),
            'provider' => fake()->randomElement(['rapidapi', 'apify', 'youtube']),
            'followers_count' => $followers,
            'following_count' => fake()->numberBetween(10, 2000),
            'posts_count' => fake()->numberBetween(0, 5000),
            'raw_payload' => ['followers' => $followers, 'source' => 'seeded'],
            'fetched_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'created_at' => now(),
        ];
    }
}
