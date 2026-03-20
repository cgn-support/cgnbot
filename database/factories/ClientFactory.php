<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $name = fake()->company().' '.fake()->randomElement(['Design Build', 'Remodeling', 'Custom Homes', 'Pools & Spas', 'Landscapes']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'domain' => 'https://'.Str::slug($name).'.com',
            'is_active' => true,
            'last_crawled_at' => null,
            'last_screenshot_at' => null,
            'settings' => [
                'crawl_frequency_hours' => 24,
                'max_depth' => 5,
                'crawl_limit' => 500,
                'concurrency' => 3,
                'excluded_patterns' => ['/wp-admin', '/wp-login.php'],
                'monitored_urls' => ['/', '/services', '/contact'],
            ],
            'slack_channel' => '#client-'.Str::slug($name),
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function recentlyCrawled(): static
    {
        return $this->state(fn () => [
            'last_crawled_at' => now()->subHours(fake()->numberBetween(1, 12)),
        ]);
    }
}
