<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class BrokenLinksCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages->filter(fn ($page) => $page->status_code >= 400)->each(function ($page) use ($crawlRun, $client, $issues) {
            $severity = $page->status_code >= 500 ? 'critical' : 'warning';

            $issues->push($this->issue(
                $crawlRun,
                $client,
                $page->url,
                'BrokenLinksCheck',
                $severity,
                ['status_code' => $page->status_code],
                confidence: 100,
            ));
        });

        return $issues;
    }
}
