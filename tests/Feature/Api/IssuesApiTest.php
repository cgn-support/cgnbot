<?php

use App\Models\Client;
use App\Models\CrawlIssue;

beforeEach(function () {
    config(['services.api.token' => 'test-token']);
});

it('lists all open issues across clients', function () {
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();

    CrawlIssue::factory()->for($clientA)->critical()->create();
    CrawlIssue::factory()->for($clientB)->warning()->create();
    CrawlIssue::factory()->for($clientA)->resolved()->create();

    $response = $this->getJson('/api/issues', ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters issues by severity', function () {
    $client = Client::factory()->create();
    CrawlIssue::factory()->for($client)->critical()->create();
    CrawlIssue::factory()->for($client)->warning()->create();

    $response = $this->getJson('/api/issues?severity=critical', ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.severity', 'critical');
});

it('filters issues by client_id', function () {
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();

    CrawlIssue::factory()->for($clientA)->create();
    CrawlIssue::factory()->for($clientB)->create();

    $response = $this->getJson("/api/issues?client_id={$clientA->id}", ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client_id', $clientA->id);
});
