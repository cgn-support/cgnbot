<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class ContentChangedCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        if ($previousPages->isEmpty()) {
            return $issues;
        }

        $previousByUrl = $previousPages->keyBy('url');

        $currentPages->each(function ($page) use ($crawlRun, $client, $previousByUrl, $issues) {
            if ($page->page_hash === null) {
                return;
            }

            $previousPage = $previousByUrl->get($page->url);

            if ($previousPage === null) {
                return;
            }

            if ($previousPage->page_hash === null) {
                return;
            }

            if ($page->page_hash === $previousPage->page_hash) {
                return;
            }

            $issues->push($this->issue(
                $crawlRun,
                $client,
                $page->url,
                'ContentChangedCheck',
                'info',
                [
                    'previous_hash' => $previousPage->page_hash,
                    'current_hash' => $page->page_hash,
                ],
                confidence: 90,
            ));
        });

        return $issues;
    }
}
