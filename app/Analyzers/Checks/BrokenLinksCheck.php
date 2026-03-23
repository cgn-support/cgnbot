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

        $clientHost = parse_url($client->resolvedDomain(), PHP_URL_HOST);

        $currentPages->filter(fn ($page) => $page->status_code >= 400)->each(function ($page) use ($crawlRun, $client, $clientHost, $issues) {
            $pageHost = parse_url($page->url, PHP_URL_HOST);
            $isInternal = $pageHost === $clientHost;

            $severity = $this->resolveSeverity($page->status_code, $isInternal);

            $issues->push($this->issue(
                $crawlRun,
                $client,
                $page->url,
                'BrokenLinksCheck',
                $severity,
                ['status_code' => $page->status_code, 'is_internal' => $isInternal],
                confidence: 100,
            ));
        });

        return $issues;
    }

    private function resolveSeverity(int $statusCode, bool $isInternal): string
    {
        if ($isInternal) {
            return $statusCode >= 500 ? 'critical' : 'warning';
        }

        return $statusCode >= 500 ? 'warning' : 'info';
    }
}
