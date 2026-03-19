<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class MultipleH1Check implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages
            ->filter(fn ($page) => $page->is_indexable && $page->h1_count > 1)
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'MultipleH1Check',
                    'info',
                    ['h1_count' => $page->h1_count],
                ));
            });

        return $issues;
    }
}
