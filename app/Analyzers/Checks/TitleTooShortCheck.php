<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class TitleTooShortCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $currentPages
            ->filter(fn ($page) => $page->is_indexable && $page->meta_title_length > 0 && $page->meta_title_length < 30)
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'TitleTooShortCheck',
                    'info',
                    ['length' => $page->meta_title_length, 'title' => $page->meta_title],
                    confidence: 85,
                ));
            });

        return $issues;
    }
}
