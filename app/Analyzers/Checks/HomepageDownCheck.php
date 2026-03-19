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
            $homepage = $currentPages->first();
        }

        if ($homepage && ($homepage->status_code < 200 || $homepage->status_code >= 300)) {
            $issues->push($this->issue(
                $crawlRun,
                $client,
                $homepage->url,
                'HomepageDownCheck',
                'critical',
                ['status_code' => $homepage->status_code],
            ));
        }

        return $issues;
    }
}
