<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class SiteWideNoindexCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $totalPages = $currentPages->count();

        if ($totalPages === 0) {
            return $issues;
        }

        $noindexCount = $currentPages->filter(fn ($page) => ! $page->is_indexable)->count();
        $ratio = $noindexCount / $totalPages;

        if ($ratio >= 0.8) {
            $issues->push($this->issue(
                $crawlRun,
                $client,
                $client->resolvedDomain(),
                'SiteWideNoindexCheck',
                'critical',
                ['noindex_ratio' => round($ratio, 2), 'pages_checked' => $totalPages],
                confidence: 95,
            ));
        }

        return $issues;
    }
}
