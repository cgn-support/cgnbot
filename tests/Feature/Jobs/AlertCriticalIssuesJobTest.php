<?php

use App\Jobs\AlertCriticalIssuesJob;
use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = Client::factory()->create([
        'domain' => 'https://example.com',
    ]);

    $this->crawlRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    CrawlerSetting::query()->delete();

    $this->settings = CrawlerSetting::create([
        'slack_webhook_url' => 'https://hooks.slack.com/services/test',
        'slack_default_channel' => '#seo-alerts',
        'alert_on_severity' => ['critical'],
        'alert_min_consecutive_detections' => 2,
        'alert_min_confidence' => 70,
    ]);
});

it('sends alert for critical issue with high confidence and consecutive detections', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $issue = CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 3,
        'confidence' => 90,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertSentCount(1);

    expect($issue->fresh()->alerted_at)->not->toBeNull();
});

it('sends alert for new critical issue with confidence >= 80 even if consecutive_detections is 1', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $issue = CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 1,
        'confidence' => 80,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertSentCount(1);

    expect($issue->fresh()->alerted_at)->not->toBeNull();
});

it('skips issues below confidence threshold', function () {
    Http::fake();

    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'severity' => 'critical',
        'consecutive_detections' => 3,
        'confidence' => 50,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertNothingSent();
});

it('skips recently alerted issues within 24 hours', function () {
    Http::fake();

    CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 3,
        'confidence' => 90,
        'alerted_at' => now()->subHours(12),
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertNothingSent();
});

it('re-alerts issues alerted more than 24 hours ago', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $issue = CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 3,
        'confidence' => 90,
        'alerted_at' => now()->subHours(25),
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertSentCount(1);

    expect($issue->fresh()->alerted_at->gt($issue->alerted_at))->toBeTrue();
});

it('early returns when no slack_webhook_url is configured', function () {
    Http::fake();

    $this->settings->update(['slack_webhook_url' => null]);

    CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 3,
        'confidence' => 90,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertNothingSent();
});

it('does not update alerted_at on failed webhook response', function () {
    Http::fake(['*' => Http::response('error', 500)]);

    $issue = CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 3,
        'confidence' => 90,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    expect($issue->fresh()->alerted_at)->toBeNull();
});

it('skips warning issues when alert_on_severity only includes critical', function () {
    Http::fake();

    CrawlIssue::factory()->warning()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'consecutive_detections' => 5,
        'confidence' => 100,
        'alerted_at' => null,
        'resolved_at' => null,
    ]);

    $job = new AlertCriticalIssuesJob($this->crawlRun, $this->client);
    $job->handle();

    Http::assertNothingSent();
});
