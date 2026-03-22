<?php

use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\CrawlerSetting;

beforeEach(function () {
    CrawlerSetting::query()->delete();
    $this->globalSettings = CrawlerSetting::factory()->create([
        'default_crawl_frequency_hours' => 24,
        'default_screenshot_frequency_hours' => 168,
        'default_max_depth' => 5,
        'default_crawl_limit' => 500,
        'default_concurrency' => 3,
        'default_slow_response_threshold_ms' => 3000,
        'default_thin_content_threshold' => 300,
        'default_visual_diff_threshold' => 15,
        'alert_on_severity' => ['critical'],
        'alert_min_consecutive_detections' => 2,
        'alert_min_confidence' => 70,
    ]);
});

it('returns global defaults when no client overrides exist', function () {
    $client = Client::factory()->create([
        'settings' => [],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['crawl_frequency_hours'])->toBe(24);
    expect($settings['max_depth'])->toBe(5);
    expect($settings['crawl_limit'])->toBe(500);
    expect($settings['concurrency'])->toBe(3);
    expect($settings['slow_response_threshold_ms'])->toBe(3000);
    expect($settings['thin_content_threshold'])->toBe(300);
    expect($settings['visual_diff_threshold'])->toBe(15);
});

it('client overrides take precedence over global defaults', function () {
    $client = Client::factory()->create([
        'settings' => [
            'crawl_frequency_hours' => 12,
            'max_depth' => 3,
            'concurrency' => 5,
        ],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['crawl_frequency_hours'])->toBe(12);
    expect($settings['max_depth'])->toBe(3);
    expect($settings['concurrency'])->toBe(5);
    expect($settings['crawl_limit'])->toBe(500);
});

it('only non-null client settings override defaults', function () {
    $client = Client::factory()->create([
        'settings' => [
            'crawl_frequency_hours' => null,
            'max_depth' => 10,
        ],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['crawl_frequency_hours'])->toBe(24);
    expect($settings['max_depth'])->toBe(10);
});

it('visual_diff_exclusion_zones defaults to empty array', function () {
    $this->globalSettings->update(['default_visual_diff_exclusion_zones' => null]);

    $client = Client::factory()->create([
        'settings' => [],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['visual_diff_exclusion_zones'])->toBe([]);
});

it('includes alert settings from global config', function () {
    $client = Client::factory()->create([
        'settings' => [],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['alert_on_severity'])->toBe(['critical']);
    expect($settings['alert_min_consecutive_detections'])->toBe(2);
    expect($settings['alert_min_confidence'])->toBe(70);
});

it('includes default excluded patterns and monitored urls', function () {
    $client = Client::factory()->create([
        'settings' => [],
    ]);

    $settings = ClientSettings::for($client);

    expect($settings['excluded_patterns'])->toContain('/wp-admin');
    expect($settings['excluded_patterns'])->toContain('/wp-login');
    expect($settings['monitored_urls'])->toContain('/');
});
