<?php

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;

beforeEach(function () {
    config(['services.api.token' => 'test-token']);
});

it('rejects requests without a bearer token', function () {
    $this->getJson('/api/clients')
        ->assertStatus(401);
});

it('rejects requests with an invalid bearer token', function () {
    $this->getJson('/api/clients', ['Authorization' => 'Bearer wrong-token'])
        ->assertStatus(401);
});

it('lists active clients with latest crawl status', function () {
    $activeClient = Client::factory()->create(['name' => 'Active Corp']);
    Client::factory()->inactive()->create(['name' => 'Inactive Corp']);

    CrawlRun::factory()->for($activeClient)->create(['status' => 'completed']);

    $response = $this->getJson('/api/clients', ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Corp')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'domain', 'is_active', 'last_crawled_at', 'latest_crawl_run'],
            ],
        ]);
});

it('shows a single client with health summary', function () {
    $client = Client::factory()->create();
    CrawlRun::factory()->for($client)->create();
    CrawlIssue::factory()->for($client)->critical()->create();
    CrawlIssue::factory()->for($client)->warning()->create();

    $response = $this->getJson("/api/clients/{$client->id}", ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.open_critical_count', 1)
        ->assertJsonPath('data.open_issues_count', 2);
});

it('lists open issues for a client', function () {
    $client = Client::factory()->create();
    CrawlIssue::factory()->for($client)->critical()->create();
    CrawlIssue::factory()->for($client)->warning()->create();
    CrawlIssue::factory()->for($client)->resolved()->create();

    $response = $this->getJson("/api/clients/{$client->id}/issues", ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters client issues by severity', function () {
    $client = Client::factory()->create();
    CrawlIssue::factory()->for($client)->critical()->create();
    CrawlIssue::factory()->for($client)->warning()->create();

    $response = $this->getJson("/api/clients/{$client->id}/issues?severity=critical", ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.severity', 'critical');
});

it('creates a single client', function () {
    $response = $this->postJson('/api/clients', [
        'name' => 'Acme Builders',
        'domain' => 'https://acmebuilders.com',
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Acme Builders')
        ->assertJsonPath('data.slug', 'acme-builders')
        ->assertJsonPath('data.domain', 'https://acmebuilders.com');

    $this->assertDatabaseHas('clients', [
        'slug' => 'acme-builders',
        'domain' => 'https://acmebuilders.com',
        'is_active' => true,
    ]);
});

it('creates a client with custom slug', function () {
    $response = $this->postJson('/api/clients', [
        'name' => 'Acme Builders',
        'domain' => 'https://acmebuilders.com',
        'slug' => 'acme-custom',
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(201)
        ->assertJsonPath('data.slug', 'acme-custom');
});

it('rejects client creation with duplicate slug', function () {
    Client::factory()->create(['slug' => 'acme-builders']);

    $response = $this->postJson('/api/clients', [
        'name' => 'Acme Builders',
        'domain' => 'https://acmebuilders.com',
        'slug' => 'acme-builders',
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('slug');
});

it('strips trailing slash from domain on creation', function () {
    $response = $this->postJson('/api/clients', [
        'name' => 'Slash Test',
        'domain' => 'https://example.com/',
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(201);
    $this->assertDatabaseHas('clients', ['domain' => 'https://example.com']);
});

it('bulk imports multiple clients', function () {
    $response = $this->postJson('/api/clients/import', [
        'clients' => [
            ['name' => 'Client A', 'domain' => 'https://client-a.com'],
            ['name' => 'Client B', 'domain' => 'https://client-b.com'],
            ['name' => 'Client C', 'domain' => 'https://client-c.com'],
        ],
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(201)
        ->assertJsonPath('message', '3 client(s) created, 0 skipped (duplicate slug).');

    $this->assertDatabaseCount('clients', 3);
});

it('skips duplicate slugs during import', function () {
    Client::factory()->create(['slug' => 'client-a']);

    $response = $this->postJson('/api/clients/import', [
        'clients' => [
            ['name' => 'Client A', 'domain' => 'https://client-a.com'],
            ['name' => 'Client B', 'domain' => 'https://client-b.com'],
        ],
    ], ['Authorization' => 'Bearer test-token']);

    $response->assertStatus(201)
        ->assertJsonPath('message', '1 client(s) created, 1 skipped (duplicate slug).')
        ->assertJsonPath('skipped_slugs.0', 'client-a');
});

it('dispatches a crawl job for a client', function () {
    $client = Client::factory()->create();

    $response = $this->postJson("/api/clients/{$client->id}/crawl", [], ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonPath('message', "Crawl dispatched for {$client->name}.");
});
