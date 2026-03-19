<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlerSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'default_crawl_frequency_hours',
        'default_screenshot_frequency_hours',
        'default_max_depth',
        'default_crawl_limit',
        'default_concurrency',
        'default_slow_response_threshold_ms',
        'default_thin_content_threshold',
        'default_visual_diff_threshold',
        'crawl_runs_to_keep',
        'resolved_issues_retention_days',
        'slack_webhook_url',
        'slack_default_channel',
        'alert_on_severity',
    ];

    protected function casts(): array
    {
        return [
            'alert_on_severity' => 'array',
        ];
    }

    public static function current(): static
    {
        return static::first() ?? static::create([]);
    }
}
