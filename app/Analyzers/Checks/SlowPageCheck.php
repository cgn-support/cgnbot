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

        $previousTimings = $previousPages->isNotEmpty()
            ? $previousPages->whereNotNull('response_time_ms')->keyBy(fn ($p) => rtrim($p->url, '/'))
            : collect();

        $currentPages
            ->filter(fn ($page) => $page->response_time_ms !== null && $page->response_time_ms > $threshold)
            ->each(function ($page) use ($crawlRun, $client, $issues, $threshold, $previousTimings) {
                $normalizedUrl = rtrim($page->url, '/');
                $previousPage = $previousTimings->get($normalizedUrl);

                // Skip pages that were already slow in the previous crawl (not a regression)
                $wasAlreadySlow = $previousPage && $previousPage->response_time_ms > $threshold;

                $context = [
                    'response_time_ms' => $page->response_time_ms,
                    'threshold_ms' => $threshold,
                    'previous_response_time_ms' => $previousPage?->response_time_ms,
                    'is_regression' => ! $wasAlreadySlow,
                ];

                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'SlowPageCheck',
                    $wasAlreadySlow ? 'info' : 'warning',
                    $context,
                    confidence: $wasAlreadySlow ? 70 : 90,
                ));
            });

        return $issues;
    }
}
