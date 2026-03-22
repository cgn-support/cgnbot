<?php

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->crawlRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
    ]);
});

it('scopeOpen filters unresolved issues', function () {
    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->resolved()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    expect(CrawlIssue::open()->count())->toBe(1);
});

it('scopeCritical filters by severity', function () {
    CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    CrawlIssue::factory()->warning()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    CrawlIssue::factory()->info()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    expect(CrawlIssue::critical()->count())->toBe(1);
});

it('scopeUnalerted filters issues without alerted_at', function () {
    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'alerted_at' => null,
    ]);

    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'alerted_at' => now(),
    ]);

    expect(CrawlIssue::unalerted()->count())->toBe(1);
});

it('scopeUnverified filters issues without verified_at', function () {
    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'verified_at' => null,
    ]);

    CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'verified_at' => now(),
    ]);

    expect(CrawlIssue::unverified()->count())->toBe(1);
});

it('markResolved sets resolved_at', function () {
    $issue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'resolved_at' => null,
    ]);

    $issue->markResolved();
    $issue->refresh();

    expect($issue->resolved_at)->not->toBeNull();
});

it('isResolved returns true when resolved_at is set', function () {
    $issue = CrawlIssue::factory()->resolved()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    expect($issue->isResolved())->toBeTrue();
});

it('isResolved returns false when resolved_at is null', function () {
    $issue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'resolved_at' => null,
    ]);

    expect($issue->isResolved())->toBeFalse();
});

it('issueKey returns url pipe issue_type format', function () {
    $issue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'url' => 'https://example.com/about',
        'issue_type' => 'missing_title',
    ]);

    expect($issue->issueKey())->toBe('https://example.com/about|missing_title');
});

it('scopes can be chained together', function () {
    CrawlIssue::factory()->critical()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'resolved_at' => null,
    ]);

    CrawlIssue::factory()->critical()->resolved()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    CrawlIssue::factory()->warning()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
        'resolved_at' => null,
    ]);

    expect(CrawlIssue::open()->critical()->count())->toBe(1);
});

it('has client relationship', function () {
    $issue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    expect($issue->client)->toBeInstanceOf(Client::class);
    expect($issue->client->id)->toBe($this->client->id);
});

it('has crawlRun relationship', function () {
    $issue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'crawl_run_id' => $this->crawlRun->id,
    ]);

    expect($issue->crawlRun)->toBeInstanceOf(CrawlRun::class);
    expect($issue->crawlRun->id)->toBe($this->crawlRun->id);
});
