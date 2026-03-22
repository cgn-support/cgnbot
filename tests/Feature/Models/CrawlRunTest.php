<?php

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->client = Client::factory()->create();
});

it('markRunning sets status and started_at', function () {
    $run = CrawlRun::factory()->pending()->create([
        'client_id' => $this->client->id,
    ]);

    $run->markRunning();
    $run->refresh();

    expect($run->status)->toBe('running');
    expect($run->started_at)->not->toBeNull();
});

it('markCompleted sets status and finished_at', function () {
    $run = CrawlRun::factory()->running()->create([
        'client_id' => $this->client->id,
    ]);

    $run->markCompleted(['pages_crawled' => 42]);
    $run->refresh();

    expect($run->status)->toBe('completed');
    expect($run->finished_at)->not->toBeNull();
    expect($run->pages_crawled)->toBe(42);
});

it('markCompleted works without counts', function () {
    $run = CrawlRun::factory()->running()->create([
        'client_id' => $this->client->id,
    ]);

    $run->markCompleted();
    $run->refresh();

    expect($run->status)->toBe('completed');
    expect($run->finished_at)->not->toBeNull();
});

it('markFailed sets status, finished_at, and error_message', function () {
    $run = CrawlRun::factory()->running()->create([
        'client_id' => $this->client->id,
    ]);

    $run->markFailed('Connection timed out');
    $run->refresh();

    expect($run->status)->toBe('failed');
    expect($run->finished_at)->not->toBeNull();
    expect($run->error_message)->toBe('Connection timed out');
});

it('durationSeconds calculates correctly', function () {
    $run = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'started_at' => now()->subMinutes(5),
        'finished_at' => now(),
    ]);

    expect($run->durationSeconds())->toBe(300);
});

it('durationSeconds returns null when started_at is missing', function () {
    $run = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'started_at' => null,
        'finished_at' => now(),
    ]);

    expect($run->durationSeconds())->toBeNull();
});

it('durationSeconds returns null when finished_at is missing', function () {
    $run = CrawlRun::factory()->running()->create([
        'client_id' => $this->client->id,
    ]);

    expect($run->durationSeconds())->toBeNull();
});

it('has pages relationship', function () {
    $run = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
    ]);

    expect($run->pages)->toBeInstanceOf(Collection::class);
});

it('has issues relationship', function () {
    $run = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
    ]);

    expect($run->issues)->toBeInstanceOf(Collection::class);
});
