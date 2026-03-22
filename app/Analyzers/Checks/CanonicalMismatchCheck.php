<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class CanonicalMismatchCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages
            ->filter(fn ($page) => $page->is_indexable && $page->status_code >= 200 && $page->status_code < 300)
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                if ($page->canonical_url === null) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'CanonicalMismatchCheck',
                        'info',
                        ['reason' => 'missing_canonical'],
                        confidence: 80,
                    ));

                    return;
                }

                if ($page->canonical_is_self === false) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'CanonicalMismatchCheck',
                        'info',
                        ['canonical_url' => $page->canonical_url, 'reason' => 'mismatch'],
                        confidence: 90,
                    ));
                }
            });

        return $issues;
    }
}
