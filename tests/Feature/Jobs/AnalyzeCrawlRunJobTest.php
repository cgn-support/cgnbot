<?php

use App\Jobs\AlertCriticalIssuesJob;
use App\Jobs\AnalyzeCrawlRunJob;
use App\Jobs\VerifyLowConfidenceIssuesJob;
use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    CrawlerSetting::query()->delete();
    CrawlerSetting::factory()->create();

    $this->client = Client::factory()->create([
        'domain' => 'https://example.com',
    ]);

    $this->crawlRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 30,
    ]);
});

it('creates issues from analyzer results', function () {
    Bus::fake();

    CrawledPage::factory()->create([
        'crawl_run_id' => $this->crawlRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 500,
        'meta_title' => 'Test',
        'h1' => 'Test',
        'h1_count' => 1,
        'word_count' => 500,
    ]);

    $job = new AnalyzeCrawlRunJob($this->crawlRun, $this->client);
    $job->handle();

    expect(CrawlIssue::where('client_id', $this->client->id)->count())->toBeGreaterThan(0);
});

it('increments consecutive_detections on repeat issues', function () {
    Bus::fake();

    $existingIssue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'url' => 'https://example.com/',
        'issue_type' => 'HomepageDownCheck',
        'severity' => 'critical',
        'consecutive_detections' => 1,
        'resolved_at' => null,
    ]);

    $newRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 30,
    ]);

    CrawledPage::factory()->create([
        'crawl_run_id' => $newRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 500,
        'meta_title' => 'Test',
        'h1' => 'Test',
        'h1_count' => 1,
        'word_count' => 500,
    ]);

    $job = new AnalyzeCrawlRunJob($newRun, $this->client);
    $job->handle();

    expect($existingIssue->fresh()->consecutive_detections)->toBe(2);
});

it('auto-resolves issues no longer detected', function () {
    Bus::fake();

    $stalIssue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'url' => 'https://example.com/about',
        'issue_type' => 'missing_title',
        'severity' => 'warning',
        'resolved_at' => null,
    ]);

    $newRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 30,
    ]);

    CrawledPage::factory()->create([
        'crawl_run_id' => $newRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/about',
        'status_code' => 200,
        'meta_title' => 'About Us',
        'meta_title_length' => 8,
        'h1' => 'About Us',
        'h1_count' => 1,
        'word_count' => 500,
        'is_indexable' => true,
    ]);

    CrawledPage::factory()->create([
        'crawl_run_id' => $newRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 200,
        'meta_title' => 'Home',
        'meta_title_length' => 4,
        'h1' => 'Welcome',
        'h1_count' => 1,
        'word_count' => 500,
        'is_indexable' => true,
    ]);

    $job = new AnalyzeCrawlRunJob($newRun, $this->client);
    $job->handle();

    expect($stalIssue->fresh()->resolved_at)->not->toBeNull();
});

it('updates CrawlRun issue counts', function () {
    Bus::fake();

    CrawledPage::factory()->create([
        'crawl_run_id' => $this->crawlRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 500,
        'meta_title' => 'Test',
        'h1' => 'Test',
        'h1_count' => 1,
        'word_count' => 500,
    ]);

    $job = new AnalyzeCrawlRunJob($this->crawlRun, $this->client);
    $job->handle();

    $this->crawlRun->refresh();

    expect($this->crawlRun->critical_issues_found)->not->toBeNull();
    expect($this->crawlRun->warning_issues_found)->not->toBeNull();
    expect($this->crawlRun->info_issues_found)->not->toBeNull();
});

it('dispatches AlertCriticalIssuesJob and VerifyLowConfidenceIssuesJob on healthy crawl', function () {
    Bus::fake();

    CrawledPage::factory()->create([
        'crawl_run_id' => $this->crawlRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 200,
        'meta_title' => 'Home',
        'h1' => 'Welcome',
        'h1_count' => 1,
        'word_count' => 500,
    ]);

    $job = new AnalyzeCrawlRunJob($this->crawlRun, $this->client);
    $job->handle();

    Bus::assertChained([
        VerifyLowConfidenceIssuesJob::class,
        AlertCriticalIssuesJob::class,
    ]);
});

it('runs limited analysis when health gate fails', function () {
    Bus::fake();

    $previousRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 100,
        'created_at' => now()->subDay(),
    ]);

    CrawledPage::factory()->count(100)->create([
        'crawl_run_id' => $previousRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    $unhealthyRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 5,
    ]);

    CrawledPage::factory()->count(5)->create([
        'crawl_run_id' => $unhealthyRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
        'url' => fn () => 'https://example.com/'.fake()->slug(),
    ]);

    $job = new AnalyzeCrawlRunJob($unhealthyRun, $this->client);
    $job->handle();

    $unhealthyRun->refresh();

    expect($unhealthyRun->context)->toHaveKey('health_gate_failed', true);
    expect($unhealthyRun->context)->toHaveKey('health_gate_reason');

    Bus::assertNothingDispatched();
});

it('does not auto-resolve issues for pages not crawled in current run', function () {
    Bus::fake();

    $existingIssue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'url' => 'https://example.com/uncrawled-page',
        'issue_type' => 'missing_title',
        'severity' => 'warning',
        'resolved_at' => null,
    ]);

    $newRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 30,
    ]);

    CrawledPage::factory()->create([
        'crawl_run_id' => $newRun->id,
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'status_code' => 200,
        'meta_title' => 'Home',
        'h1' => 'Welcome',
        'h1_count' => 1,
        'word_count' => 500,
    ]);

    $job = new AnalyzeCrawlRunJob($newRun, $this->client);
    $job->handle();

    expect($existingIssue->fresh()->resolved_at)->toBeNull();
});
