<?php

namespace App\Jobs;

use App\Crawlers\ClientCrawlObserver;
use App\Crawlers\ClientCrawlProfile;
use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\Crawler\Crawler;

class CrawlClientJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(
        public Client $client,
        public bool $triggeredManually = false,
    ) {
        $this->queue = 'crawl';
    }

    public function handle(): void
    {
        $settings = ClientSettings::for($this->client);

        Log::info("Crawl starting for {$this->client->name}", [
            'client_id' => $this->client->id,
            'domain' => $this->client->resolvedDomain(),
            'max_depth' => $settings['max_depth'],
            'crawl_limit' => $settings['crawl_limit'],
            'concurrency' => $settings['concurrency'],
            'manual' => $this->triggeredManually,
        ]);

        $run = CrawlRun::create([
            'client_id' => $this->client->id,
            'status' => 'pending',
            'triggered_manually' => $this->triggeredManually,
        ]);

        $run->markRunning();

        try {
            $baseHost = parse_url($this->client->resolvedDomain(), PHP_URL_HOST);

            $observer = new ClientCrawlObserver(
                crawlRun: $run,
                clientId: $this->client->id,
                baseDomain: $this->client->resolvedDomain(),
            );

            $profile = new ClientCrawlProfile(
                baseHost: $baseHost,
                excludedPatterns: $settings['excluded_patterns'],
            );

            Crawler::create($this->client->resolvedDomain())
                ->addObserver($observer)
                ->crawlProfile($profile)
                ->depth($settings['max_depth'])
                ->limit($settings['crawl_limit'])
                ->concurrency($settings['concurrency'])
                ->delay(250)
                ->start();

            $run->markCompleted([
                'pages_crawled' => $observer->getPagesCrawled(),
            ]);

            $this->client->update(['last_crawled_at' => now()]);

            AnalyzeCrawlRunJob::dispatch($run, $this->client);
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());

            throw $e;
        }
    }
}
