<?php

namespace App\Console\Commands;

use App\Jobs\CrawlClientJob;
use App\Models\Client;
use Illuminate\Console\Command;

class DispatchPendingCrawls extends Command
{
    protected $signature = 'watchdog:dispatch-crawls';

    protected $description = 'Dispatch crawl jobs for clients that are due';

    public function handle(): void
    {
        $clients = Client::dueForCrawl()
            ->orderBy('last_crawled_at')
            ->limit(5)
            ->get();

        foreach ($clients as $client) {
            CrawlClientJob::dispatch($client);
            $this->info("Dispatched crawl for: {$client->name}");
        }

        $this->info("Dispatched {$clients->count()} crawl(s).");
    }
}
