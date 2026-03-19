<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class MissingTitleCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages
            ->filter(fn ($page) => $page->is_indexable)
            ->filter(fn ($page) => empty($page->meta_title))
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'MissingTitleCheck',
                    'warning',
                ));
            });

        return $issues;
    }
}
