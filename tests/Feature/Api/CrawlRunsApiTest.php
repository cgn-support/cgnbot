<?php

use App\Models\Client;
use App\Models\CrawlRun;

beforeEach(function () {
    config(['services.api.token' => 'test-token']);
});

it('returns the latest 10 crawl runs', function () {
    $client = Client::factory()->create();
    CrawlRun::factory()->for($client)->count(15)->create();

    $response = $this->getJson('/api/crawl-runs/latest', ['Authorization' => 'Bearer test-token']);

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'client_id', 'status', 'pages_crawled', 'started_at', 'finished_at', 'client'],
            ],
        ]);
});
