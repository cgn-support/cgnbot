<?php

namespace App\Console\Commands;

use App\Models\CrawlRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStaleCrawlRuns extends Command
{
    protected $signature = 'watchdog:cleanup-stale-runs {--hours=2 : Hours before a running crawl is considered stale}';

    protected $description = 'Mark stale crawl runs (stuck in running status) as failed';

    public function handle(): void
    {
        $hours = (int) $this->option('hours');

        $staleRuns = CrawlRun::where('status', 'running')
            ->where('updated_at', '<', now()->subHours($hours))
            ->get();

        if ($staleRuns->isEmpty()) {
            $this->info('No stale crawl runs found.');

            return;
        }

        foreach ($staleRuns as $run) {
            $run->markFailed("Crawl timed out: no progress for {$hours}+ hours");
            Log::warning("Marked stale crawl run #{$run->id} as failed (client_id: {$run->client_id})");
            $this->warn("Marked crawl run #{$run->id} as failed (client: {$run->client_id})");
        }

        $this->info("Cleaned up {$staleRuns->count()} stale crawl run(s).");
    }
}
