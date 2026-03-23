<?php

use App\Events\CrawlCompleted;
use App\Events\CrawlFailed;
use App\Events\CriticalIssueDetected;
use App\Listeners\WebhookListener;
use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CrawlerSetting::query()->delete();
});

it('sends a webhook when a crawl completes', function () {
    Http::fake();

    CrawlerSetting::factory()->create(['webhook_url' => 'https://hooks.example.com/test']);

    $client = Client::factory()->create();
    $run = CrawlRun::factory()->for($client)->create();

    $listener = new WebhookListener;
    $listener->handleCrawlCompleted(new CrawlCompleted($run, $client));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.example.com/test'
            && $request['event'] === 'crawl.completed'
            && isset($request['client'])
            && isset($request['crawl_run'])
            && isset($request['timestamp']);
    });
});

it('sends a webhook when a crawl fails', function () {
    Http::fake();

    CrawlerSetting::factory()->create(['webhook_url' => 'https://hooks.example.com/test']);

    $client = Client::factory()->create();
    $run = CrawlRun::factory()->failed()->for($client)->create();

    $listener = new WebhookListener;
    $listener->handleCrawlFailed(new CrawlFailed($run, $client, 'Connection timeout'));

    Http::assertSent(function ($request) {
        return $request['event'] === 'crawl.failed'
            && $request['crawl_run']['error_message'] === 'Connection timeout';
    });
});

it('sends a webhook when a critical issue is detected', function () {
    Http::fake();

    CrawlerSetting::factory()->create(['webhook_url' => 'https://hooks.example.com/test']);

    $client = Client::factory()->create();
    $run = CrawlRun::factory()->for($client)->create();
    $issue = CrawlIssue::factory()->for($client)->critical()->create(['crawl_run_id' => $run->id]);

    $listener = new WebhookListener;
    $listener->handleCriticalIssueDetected(new CriticalIssueDetected($issue, $run, $client));

    Http::assertSent(function ($request) {
        return $request['event'] === 'critical_issue.detected'
            && isset($request['issue']);
    });
});

it('does nothing when no webhook url is configured', function () {
    Http::fake();

    CrawlerSetting::factory()->create(['webhook_url' => null]);

    $client = Client::factory()->create();
    $run = CrawlRun::factory()->for($client)->create();

    $listener = new WebhookListener;
    $listener->handleCrawlCompleted(new CrawlCompleted($run, $client));

    Http::assertNothingSent();
});
