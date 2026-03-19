<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class PageDisappearedCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        if ($previousPages->isEmpty()) {
            return $issues;
        }

        $currentUrls = $currentPages->pluck('url')->map(fn ($url) => rtrim($url, '/'))->toArray();
        $currentUrlMap = array_flip($currentUrls);

        $previousPages
            ->filter(fn ($page) => $page->is_indexable && $page->status_code < 400)
            ->each(function ($page) use ($crawlRun, $client, $issues, $currentPages, $currentUrlMap) {
                $normalizedUrl = rtrim($page->url, '/');

                if (! isset($currentUrlMap[$normalizedUrl])) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'PageDisappearedCheck',
                        'warning',
                        ['last_seen_status_code' => $page->status_code],
                    ));

                    return;
                }

                $currentPage = $currentPages->first(fn ($p) => rtrim($p->url, '/') === $normalizedUrl);

                if ($currentPage && $currentPage->status_code === 404) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'PageDisappearedCheck',
                        'warning',
                        ['current_status_code' => 404],
                    ));
                }
            });

        return $issues;
    }
}
