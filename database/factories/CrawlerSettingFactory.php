<?php

namespace Database\Factories;

use App\Models\CrawlerSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrawlerSetting> */
class CrawlerSettingFactory extends Factory
{
    protected $model = CrawlerSetting::class;

    public function definition(): array
    {
        return [
            'default_crawl_frequency_hours' => 24,
            'default_screenshot_frequency_hours' => 168,
            'default_max_depth' => 5,
            'default_crawl_limit' => 500,
            'default_concurrency' => 3,
            'default_slow_response_threshold_ms' => 3000,
            'default_thin_content_threshold' => 300,
            'default_visual_diff_threshold' => 15,
            'crawl_runs_to_keep' => 10,
            'resolved_issues_retention_days' => 90,
            'slack_webhook_url' => null,
            'slack_default_channel' => null,
            'alert_on_severity' => ['critical'],
        ];
    }
}
