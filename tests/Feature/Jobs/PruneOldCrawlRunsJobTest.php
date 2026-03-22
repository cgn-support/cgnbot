<?php

use App\Jobs\PruneOldCrawlRunsJob;
use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CrawlerSetting::query()->delete();

    CrawlerSetting::create([
        'crawl_runs_to_keep' => 3,
        'resolved_issues_retention_days' => 90,
        'alert_on_severity' => ['critical'],
    ]);

    $this->client = Client::factory()->create();
});

it('keeps the correct number of recent completed runs', function () {
    // The prune job uses NOW() in raw SQL which is MySQL-specific
    // Register SQLite NOW() function for compatibility
    DB::connection()->getPdo()->sqliteCreateFunction('NOW', function () {
        return date('Y-m-d H:i:s');
    });

    $runs = collect();
    for ($index = 0; $index < 5; $index++) {
        $runs->push(CrawlRun::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'completed',
            'created_at' => now()->subDays(5 - $index),
        ]));
    }

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    $remaining = CrawlRun::where('client_id', $this->client->id)
        ->where('status', 'completed')
        ->count();

    expect($remaining)->toBe(3);

    $keptIds = CrawlRun::where('client_id', $this->client->id)->pluck('id');
    expect($keptIds)->toContain($runs[4]->id);
    expect($keptIds)->toContain($runs[3]->id);
    expect($keptIds)->toContain($runs[2]->id);
});

it('deletes crawled pages for pruned runs', function () {
    DB::connection()->getPdo()->sqliteCreateFunction('NOW', function () {
        return date('Y-m-d H:i:s');
    });

    $oldRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
        'created_at' => now()->subDays(10),
    ]);

    CrawledPage::factory()->count(5)->create([
        'crawl_run_id' => $oldRun->id,
        'client_id' => $this->client->id,
    ]);

    for ($index = 0; $index < 3; $index++) {
        CrawlRun::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'completed',
            'created_at' => now()->subDays(3 - $index),
        ]);
    }

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    $this->assertDatabaseMissing('crawl_runs', ['id' => $oldRun->id]);
    expect(CrawledPage::where('crawl_run_id', $oldRun->id)->count())->toBe(0);
});

it('purges resolved issues older than retention period', function () {
    $oldResolvedIssue = CrawlIssue::factory()->resolved()->create([
        'client_id' => $this->client->id,
        'resolved_at' => now()->subDays(91),
    ]);

    $recentResolvedIssue = CrawlIssue::factory()->resolved()->create([
        'client_id' => $this->client->id,
        'resolved_at' => now()->subDays(30),
    ]);

    $openIssue = CrawlIssue::factory()->create([
        'client_id' => $this->client->id,
        'resolved_at' => null,
    ]);

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    $this->assertDatabaseMissing('crawl_issues', ['id' => $oldResolvedIssue->id]);
    $this->assertDatabaseHas('crawl_issues', ['id' => $recentResolvedIssue->id]);
    $this->assertDatabaseHas('crawl_issues', ['id' => $openIssue->id]);
});

it('does not touch running or pending runs', function () {
    DB::connection()->getPdo()->sqliteCreateFunction('NOW', function () {
        return date('Y-m-d H:i:s');
    });

    $runningRun = CrawlRun::factory()->running()->create([
        'client_id' => $this->client->id,
    ]);

    $pendingRun = CrawlRun::factory()->pending()->create([
        'client_id' => $this->client->id,
    ]);

    for ($index = 0; $index < 5; $index++) {
        CrawlRun::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'completed',
            'created_at' => now()->subDays(5 - $index),
        ]);
    }

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    $this->assertDatabaseHas('crawl_runs', ['id' => $runningRun->id]);
    $this->assertDatabaseHas('crawl_runs', ['id' => $pendingRun->id]);
});

it('does not prune when client has fewer runs than keep count', function () {
    CrawlRun::factory()->count(2)->create([
        'client_id' => $this->client->id,
        'status' => 'completed',
    ]);

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    expect(CrawlRun::where('client_id', $this->client->id)->count())->toBe(2);
});

it('does not prune inactive client runs', function () {
    $inactiveClient = Client::factory()->inactive()->create();

    CrawlRun::factory()->count(5)->create([
        'client_id' => $inactiveClient->id,
        'status' => 'completed',
    ]);

    $job = new PruneOldCrawlRunsJob;
    $job->handle();

    expect(CrawlRun::where('client_id', $inactiveClient->id)->count())->toBe(5);
});
