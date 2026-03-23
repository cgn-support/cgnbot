<?php

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use App\Services\HealthReportService;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->service = new HealthReportService;
});

it('generates report with correct summary counts', function () {
    $completedRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 45,
        'critical_issues_found' => 2,
        'warning_issues_found' => 3,
        'info_issues_found' => 1,
        'created_at' => now()->subDays(5),
    ]);

    $failedRun = CrawlRun::factory()->failed()->create([
        'client_id' => $this->client->id,
        'created_at' => now()->subDays(3),
    ]);

    CrawlIssue::factory()->critical()->count(2)->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $completedRun->id,
        'detected_at' => now()->subDays(5),
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->warning()->count(3)->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $completedRun->id,
        'detected_at' => now()->subDays(5),
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->info()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $completedRun->id,
        'detected_at' => now()->subDays(5),
        'resolved_at' => now()->subDay(),
    ]);

    $report = $this->service->generate($this->client, 30);

    expect($report['summary']['total_crawls'])->toBe(2);
    expect($report['summary']['successful_crawls'])->toBe(1);
    expect($report['summary']['failed_crawls'])->toBe(1);
    expect($report['summary']['total_pages_crawled'])->toBe(45);
    expect($report['summary']['current_open_issues']['critical'])->toBe(2);
    expect($report['summary']['current_open_issues']['warning'])->toBe(3);
    expect($report['summary']['current_open_issues']['info'])->toBe(0);
    expect($report['summary']['issues_detected'])->toBe(6);
    expect($report['summary']['issues_resolved'])->toBe(1);
});

it('handles client with no crawl history', function () {
    $report = $this->service->generate($this->client, 30);

    expect($report['client']['name'])->toBe($this->client->name);
    expect($report['summary']['total_crawls'])->toBe(0);
    expect($report['summary']['successful_crawls'])->toBe(0);
    expect($report['summary']['failed_crawls'])->toBe(0);
    expect($report['summary']['total_pages_crawled'])->toBe(0);
    expect($report['summary']['current_open_issues'])->toBe(['critical' => 0, 'warning' => 0, 'info' => 0]);
    expect($report['summary']['issues_detected'])->toBe(0);
    expect($report['summary']['issues_resolved'])->toBe(0);
    expect($report['crawl_history'])->toBe([]);
    expect($report['top_issues'])->toBe([]);
    expect($report['open_issues'])->toBe([]);
});

it('respects period parameter', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 50,
        'created_at' => now()->subDays(5),
    ]);

    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 40,
        'created_at' => now()->subDays(15),
    ]);

    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 30,
        'created_at' => now()->subDays(25),
    ]);

    $sevenDayReport = $this->service->generate($this->client, 7);
    expect($sevenDayReport['summary']['total_crawls'])->toBe(1);
    expect($sevenDayReport['period']['days'])->toBe(7);

    $thirtyDayReport = $this->service->generate($this->client, 30);
    expect($thirtyDayReport['summary']['total_crawls'])->toBe(3);
    expect($thirtyDayReport['period']['days'])->toBe(30);
});

it('returns crawl history ordered by most recent first', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'created_at' => now()->subDays(10),
    ]);

    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'created_at' => now()->subDays(2),
    ]);

    $report = $this->service->generate($this->client, 30);

    expect($report['crawl_history'])->toHaveCount(2);
    expect($report['crawl_history'][0]['date'] > $report['crawl_history'][1]['date'])->toBeTrue();
});

it('groups open issues by severity with critical first', function () {
    $crawlRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawlIssue::factory()->info()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $crawlRun->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $crawlRun->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->warning()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $crawlRun->id,
        'resolved_at' => null,
    ]);

    $report = $this->service->generate($this->client, 30);

    expect($report['open_issues'])->toHaveCount(3);
    expect($report['open_issues'][0]['severity'])->toBe('critical');
    expect($report['open_issues'][1]['severity'])->toBe('warning');
    expect($report['open_issues'][2]['severity'])->toBe('info');
});
