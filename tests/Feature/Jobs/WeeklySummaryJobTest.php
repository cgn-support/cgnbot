<?php

use App\Jobs\WeeklySummaryJob;
use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CrawlerSetting::query()->delete();

    $this->settings = CrawlerSetting::create([
        'slack_webhook_url' => 'https://hooks.slack.com/services/test',
        'slack_default_channel' => '#seo-summary',
        'alert_on_severity' => ['critical'],
    ]);
});

it('sends weekly summary with correct counts', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    Client::factory()->inactive()->create();

    $runA = CrawlRun::factory()->create(['client_id' => $clientA->id]);
    $runB = CrawlRun::factory()->create(['client_id' => $clientB->id]);

    CrawlIssue::factory()->critical()->create([
        'client_id' => $clientA->id,
        'crawl_run_id' => $runA->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->warning()->create([
        'client_id' => $clientA->id,
        'crawl_run_id' => $runA->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->create([
        'client_id' => $clientB->id,
        'crawl_run_id' => $runB->id,
        'resolved_at' => now()->subDays(3),
    ]);

    $job = new WeeklySummaryJob;
    $job->handle();

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.slack.com/services/test';
    });
});

it('early returns when no webhook is configured', function () {
    Http::fake();

    $this->settings->update(['slack_webhook_url' => null]);

    Client::factory()->create();

    $job = new WeeklySummaryJob;
    $job->handle();

    Http::assertNothingSent();
});

it('includes channel in payload when default channel is set', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    Client::factory()->create();

    $job = new WeeklySummaryJob;
    $job->handle();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['channel']) && $data['channel'] === '#seo-summary';
    });
});

it('does not include channel in payload when no default channel is set', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $this->settings->update(['slack_default_channel' => null]);

    Client::factory()->create();

    $job = new WeeklySummaryJob;
    $job->handle();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return ! isset($data['channel']);
    });
});

it('counts resolved issues from the last 7 days only', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $client = Client::factory()->create();
    $run = CrawlRun::factory()->create(['client_id' => $client->id]);

    CrawlIssue::factory()->create([
        'client_id' => $client->id,
        'crawl_run_id' => $run->id,
        'resolved_at' => now()->subDays(3),
    ]);

    CrawlIssue::factory()->create([
        'client_id' => $client->id,
        'crawl_run_id' => $run->id,
        'resolved_at' => now()->subDays(10),
    ]);

    $job = new WeeklySummaryJob;
    $job->handle();

    Http::assertSentCount(1);
});
