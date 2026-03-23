<?php

namespace App\Events;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrawlCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CrawlRun $crawlRun,
        public readonly Client $client,
    ) {}
}
