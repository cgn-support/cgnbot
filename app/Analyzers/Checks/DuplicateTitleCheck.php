<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class DuplicateTitleCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $minDuplicates = $settings['duplicate_title_threshold'] ?? 3;

        $indexablePages = $currentPages
            ->filter(fn ($page) => $page->is_indexable && ! empty($page->meta_title))
            ->reject(fn ($page) => preg_match('#/page/\d+/?$#', $page->url));

        $grouped = $indexablePages->groupBy('meta_title');

        foreach ($grouped as $title => $pages) {
            if ($pages->count() >= $minDuplicates) {
                foreach ($pages as $page) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'DuplicateTitleCheck',
                        'warning',
                        ['title' => $title, 'shared_with_count' => $pages->count()],
                        confidence: 85,
                    ));
                }
            }
        }

        return $issues;
    }
}
