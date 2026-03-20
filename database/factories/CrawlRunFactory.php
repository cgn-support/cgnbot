<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrawlRun> */
class CrawlRunFactory extends Factory
{
    protected $model = CrawlRun::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'client_id' => Client::factory(),
            'status' => 'completed',
            'triggered_manually' => false,
            'pages_crawled' => fake()->numberBetween(10, 200),
            'pages_with_issues' => fake()->numberBetween(0, 20),
            'critical_issues_found' => fake()->numberBetween(0, 5),
            'warning_issues_found' => fake()->numberBetween(0, 15),
            'info_issues_found' => fake()->numberBetween(0, 10),
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(30, 300).' seconds'),
            'error_message' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'pages_crawled' => 0,
            'pages_with_issues' => 0,
            'critical_issues_found' => 0,
            'warning_issues_found' => 0,
            'info_issues_found' => 0,
            'error_message' => 'Connection timeout after 30 seconds',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'pages_crawled' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => 'running',
            'pages_crawled' => fake()->numberBetween(1, 50),
            'finished_at' => null,
        ]);
    }
}
