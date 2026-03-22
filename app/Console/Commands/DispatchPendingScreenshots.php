<?php

namespace App\Console\Commands;

use App\Jobs\ScreenshotClientJob;
use App\Models\Client;
use Illuminate\Console\Command;

class DispatchPendingScreenshots extends Command
{
    protected $signature = 'watchdog:dispatch-screenshots';

    protected $description = 'Dispatch screenshot jobs for clients that are due';

    public function handle(): void
    {
        $clients = Client::dueForScreenshot()
            ->orderBy('last_screenshot_at')
            ->limit(3)
            ->get();

        foreach ($clients as $client) {
            ScreenshotClientJob::dispatch($client);
            $this->info("Dispatched screenshots for: {$client->name}");
        }

        $this->info("Dispatched {$clients->count()} screenshot job(s).");
    }
}
