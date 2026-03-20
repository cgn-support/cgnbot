<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrawlIssue> */
class CrawlIssueFactory extends Factory
{
    protected $model = CrawlIssue::class;

    public function definition(): array
    {
        $issueTypes = [
            'missing_title',
            'missing_description',
            'missing_h1',
            'duplicate_title',
            'duplicate_description',
            'broken_link',
            'slow_response',
            'thin_content',
            'missing_schema',
            'redirect_chain',
            'noindex',
            'missing_canonical',
        ];

        return [
            'client_id' => Client::factory(),
            'crawl_run_id' => CrawlRun::factory(),
            'url' => fake()->url(),
            'issue_type' => fake()->randomElement($issueTypes),
            'severity' => fake()->randomElement(['critical', 'warning', 'info']),
            'context' => [
                'message' => fake()->sentence(),
                'expected' => fake()->word(),
                'actual' => fake()->word(),
            ],
            'detected_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'resolved_at' => null,
            'alerted_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'resolved_at' => now(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => 'critical']);
    }

    public function warning(): static
    {
        return $this->state(fn () => ['severity' => 'warning']);
    }

    public function info(): static
    {
        return $this->state(fn () => ['severity' => 'info']);
    }
}
