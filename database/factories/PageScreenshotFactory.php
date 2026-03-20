<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\PageScreenshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageScreenshot> */
class PageScreenshotFactory extends Factory
{
    protected $model = PageScreenshot::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'url' => fake()->url(),
            'file_path' => 'screenshots/'.fake()->uuid().'.png',
            'disk' => 'local',
            'siteshot_job_id' => fake()->uuid(),
            'viewport_width' => 1440,
            'full_page' => true,
            'previous_screenshot_id' => null,
            'diff_percentage' => fake()->randomFloat(2, 0, 50),
            'diff_image_path' => null,
            'captured_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => null,
        ];
    }

    public function withDiff(): static
    {
        return $this->state(fn () => [
            'diff_percentage' => fake()->randomFloat(2, 5, 80),
            'diff_image_path' => 'screenshots/diffs/'.fake()->uuid().'.png',
        ]);
    }
}
