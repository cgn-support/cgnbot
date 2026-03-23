<?php

namespace App\Events;

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CriticalIssueDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CrawlIssue $issue,
        public readonly CrawlRun $crawlRun,
        public readonly Client $client,
    ) {}
}
