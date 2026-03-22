<?php

use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlRun;
use App\Services\CrawlHealthGate;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->healthGate = new CrawlHealthGate;
});

it('passes on first crawl when no previous run exists', function () {
    $crawlRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawledPage::factory()->count(30)->create([
        'crawl_run_id' => $crawlRun->id,
        'client_id' => $this->client->id,
    ]);

    $result = $this->healthGate->evaluate($crawlRun);

    expect($result->healthy)->toBeTrue();
    expect($result->reason)->toBeNull();
});

it('passes when previous run had fewer than 20 pages', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 15,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawledPage::factory()->count(5)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    expect($result->healthy)->toBeTrue();
});

it('passes when current crawl has similar page count to previous', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 50,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawledPage::factory()->count(45)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    expect($result->healthy)->toBeTrue();
});

it('fails when page count drops more than 50 percent', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 100,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawledPage::factory()->count(10)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    expect($result->healthy)->toBeFalse();
    expect($result->reason)->toContain('Page count dropped');
});

it('fails when more than 30 percent of pages have status code 0', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 50,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    // 34 OK + 16 failed = 50 total (32% failure rate, > 30%)
    // 50 total vs 50 previous = no page drop
    CrawledPage::factory()->count(34)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    CrawledPage::factory()->count(16)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 0,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    expect($result->healthy)->toBeFalse();
    expect($result->reason)->toContain('failure rate');
});

it('passes when exactly 30 percent of pages have status code 0', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 100,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    // 70 OK + 30 failed = 100 total (exactly 30%, not > 30%)
    CrawledPage::factory()->count(70)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    CrawledPage::factory()->count(30)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 0,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    // 30/100 = 0.3, which is NOT > 0.3, so should pass
    expect($result->healthy)->toBeTrue();
});

it('passes when page count is exactly 50 percent of previous', function () {
    CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'pages_crawled' => 100,
        'created_at' => now()->subDay(),
    ]);

    $currentRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawledPage::factory()->count(50)->create([
        'crawl_run_id' => $currentRun->id,
        'client_id' => $this->client->id,
        'status_code' => 200,
    ]);

    $result = $this->healthGate->evaluate($currentRun);

    // 50 < (100 * 0.5) = 50 < 50 = false, so should pass
    expect($result->healthy)->toBeTrue();
});
