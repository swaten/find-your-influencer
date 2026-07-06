<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\ProfileStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    public function definition(): array
    {
        $platform = fake()->randomElement(Platform::cases());
        $username = fake()->unique()->userName();
        $status = fake()->randomElement(ProfileStatus::cases());
        $fetched = $status === ProfileStatus::Fetched;

        return [
            'platform' => $platform,
            'username' => $username,
            'username_normalized' => mb_strtolower($username),
            'external_id' => $fetched ? (string) fake()->numberBetween(1000000, 999999999) : null,
            'display_name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(200, 200, 'people'),
            'status' => $status,
            'consecutive_failures' => $status === ProfileStatus::Failed ? fake()->numberBetween(1, 9) : 0,
            'last_fetch_attempted_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'last_fetched_at' => $fetched ? fake()->dateTimeBetween('-2 days', 'now') : null,
            'last_error' => $status === ProfileStatus::Failed ? fake()->randomElement([
                'timeout after 8000ms', 'HTTP 429 rate limited', 'HTTP 500 upstream error',
            ]) : null,
            'last_followers_count' => $fetched ? fake()->numberBetween(100, 5_000_000) : null,
            'last_following_count' => $fetched ? fake()->numberBetween(10, 2000) : null,
            'last_posts_count' => $fetched ? fake()->numberBetween(0, 5000) : null,
            'added_by' => User::query()->inRandomOrder()->value('id') ?? User::factory()->create()->id,
        ];
    }
}
