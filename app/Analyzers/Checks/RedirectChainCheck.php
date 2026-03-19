<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class RedirectChainCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages
            ->filter(fn ($page) => $page->redirect_count > 1)
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'RedirectChainCheck',
                    'warning',
                    ['redirect_count' => $page->redirect_count, 'final_url' => $page->redirect_url],
                ));
            });

        return $issues;
    }
}
