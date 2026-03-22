<?php

namespace App\Crawlers;

use App\Models\Client;
use App\Models\CrawlerSetting;

class ClientSettings
{
    public static function for(Client $client): array
    {
        $global = CrawlerSetting::current();

        $defaults = [
            'crawl_frequency_hours' => $global->default_crawl_frequency_hours,
            'screenshot_frequency_hours' => $global->default_screenshot_frequency_hours,
            'max_depth' => $global->default_max_depth,
            'crawl_limit' => $global->default_crawl_limit,
            'concurrency' => $global->default_concurrency,
            'slow_response_threshold_ms' => $global->default_slow_response_threshold_ms,
            'thin_content_threshold' => $global->default_thin_content_threshold,
            'visual_diff_threshold' => $global->default_visual_diff_threshold,
            'alert_on_severity' => $global->alert_on_severity,
            'alert_min_consecutive_detections' => $global->alert_min_consecutive_detections ?? 2,
            'alert_min_confidence' => $global->alert_min_confidence ?? 70,
            'monitored_urls' => ['/'],
            'excluded_patterns' => [
                '/wp-admin', '/wp-login', '/wp-json',
                '?s=', '/feed', '/page/',
                '.xml', '.pdf', '.jpg', '.png', '.gif', '.css', '.js',
            ],
        ];

        $clientSettings = array_filter($client->settings ?? [], fn ($value) => $value !== null);

        return array_merge($defaults, $clientSettings);
    }
}
