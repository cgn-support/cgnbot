<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrawledPage> */
class CrawledPageFactory extends Factory
{
    protected $model = CrawledPage::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);
        $description = fake()->sentence(15);

        return [
            'crawl_run_id' => CrawlRun::factory(),
            'client_id' => Client::factory(),
            'url' => fake()->url().'/'.fake()->slug(3),
            'status_code' => 200,
            'redirect_url' => null,
            'redirect_count' => 0,
            'canonical_url' => null,
            'canonical_is_self' => true,
            'meta_title' => $title,
            'meta_title_length' => strlen($title),
            'meta_description' => $description,
            'meta_description_length' => strlen($description),
            'h1' => fake()->sentence(4),
            'h1_count' => 1,
            'word_count' => fake()->numberBetween(300, 2000),
            'is_indexable' => true,
            'in_sitemap' => true,
            'has_schema_markup' => fake()->boolean(70),
            'schema_types' => ['LocalBusiness', 'WebPage'],
            'internal_links_count' => fake()->numberBetween(5, 50),
            'external_links_count' => fake()->numberBetween(0, 10),
            'broken_links_count' => 0,
            'response_time_ms' => fake()->numberBetween(100, 2500),
            'page_hash' => md5(fake()->text()),
            'first_seen_at' => fake()->dateTimeBetween('-90 days', '-30 days'),
            'last_seen_at' => now(),
        ];
    }

    public function broken(): static
    {
        return $this->state(fn () => [
            'status_code' => fake()->randomElement([404, 500, 503]),
            'is_indexable' => false,
        ]);
    }

    public function redirect(): static
    {
        return $this->state(fn () => [
            'status_code' => 301,
            'redirect_url' => fake()->url(),
            'redirect_count' => 1,
        ]);
    }

    public function thinContent(): static
    {
        return $this->state(fn () => [
            'word_count' => fake()->numberBetween(10, 100),
        ]);
    }
}
