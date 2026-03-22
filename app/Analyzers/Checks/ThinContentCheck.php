<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class ThinContentCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();
        $threshold = $settings['thin_content_threshold'] ?? 300;

        $currentPages
            ->filter(fn ($page) => $page->is_indexable && $page->word_count > 0 && $page->word_count < $threshold)
            ->each(function ($page) use ($crawlRun, $client, $issues, $threshold) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'ThinContentCheck',
                    'warning',
                    ['word_count' => $page->word_count, 'threshold' => $threshold],
                    confidence: 50,
                ));
            });

        return $issues;
    }
}
