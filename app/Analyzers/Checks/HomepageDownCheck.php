<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class HomepageDownCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $homepageUrl = rtrim($client->resolvedDomain(), '/').'/';
        $homepage = $currentPages->first(fn ($page) => rtrim($page->url, '/').'/' === $homepageUrl);

        if (! $homepage) {
            $issues->push($this->issue(
                $crawlRun,
                $client,
                $homepageUrl,
                'HomepageMissingFromCrawl',
                'critical',
                ['reason' => 'Homepage URL not found in crawl results'],
                confidence: 80,
            ));

            return $issues;
        }

        if ($homepage->status_code < 200 || $homepage->status_code >= 300) {
            $issues->push($this->issue(
                $crawlRun,
                $client,
                $homepage->url,
                'HomepageDownCheck',
                'critical',
                ['status_code' => $homepage->status_code],
                confidence: 100,
            ));
        }

        return $issues;
    }
}
