<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

interface CrawlCheck
{
    public function run(
        CrawlRun $crawlRun,
        Client $client,
        Collection $currentPages,
        Collection $previousPages,
        array $settings,
    ): Collection;
}
