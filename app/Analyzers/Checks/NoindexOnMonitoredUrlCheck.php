<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class NoindexOnMonitoredUrlCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();
        $monitoredUrls = $settings['monitored_urls'] ?? ['/'];
        $domain = $client->resolvedDomain();

        foreach ($monitoredUrls as $path) {
            $fullUrl = $domain.'/'.ltrim($path, '/');

            $page = $currentPages->first(function ($p) use ($fullUrl) {
                return rtrim($p->url, '/') === rtrim($fullUrl, '/');
            });

            if ($page && $page->is_indexable === false) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'NoindexOnMonitoredUrlCheck',
                    'critical',
                    ['monitored_url' => $path],
                ));
            }
        }

        return $issues;
    }
}
