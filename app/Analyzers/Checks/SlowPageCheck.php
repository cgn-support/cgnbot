<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class SlowPageCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();
        $threshold = $settings['slow_response_threshold_ms'] ?? 3000;

        $currentPages
            ->filter(fn ($page) => $page->response_time_ms !== null && $page->response_time_ms > $threshold)
            ->each(function ($page) use ($crawlRun, $client, $issues, $threshold) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'SlowPageCheck',
                    'warning',
                    ['response_time_ms' => $page->response_time_ms, 'threshold_ms' => $threshold],
                ));
            });

        return $issues;
    }
}
