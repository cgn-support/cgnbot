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

        $currentUrls = $currentPages->pluck('url')->map(fn ($url) => $this->normalizeUrl($url))->toArray();
        $currentUrlMap = array_flip($currentUrls);

        $previousPages
            ->filter(fn ($page) => $page->is_indexable && $page->status_code < 400)
            ->each(function ($page) use ($crawlRun, $client, $issues, $currentPages, $currentUrlMap) {
                $normalizedUrl = $this->normalizeUrl($page->url);

                if (! isset($currentUrlMap[$normalizedUrl])) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'PageDisappearedCheck',
                        'warning',
                        ['last_seen_status_code' => $page->status_code],
                        confidence: 80,
                    ));

                    return;
                }

                $currentPage = $currentPages->first(fn ($p) => $this->normalizeUrl($p->url) === $normalizedUrl);

                if ($currentPage && $currentPage->status_code === 404) {
                    $issues->push($this->issue(
                        $crawlRun,
                        $client,
                        $page->url,
                        'PageDisappearedCheck',
                        'warning',
                        ['current_status_code' => 404],
                        confidence: 80,
                    ));
                }
            });

        return $issues;
    }

    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = rtrim($parsed['path'] ?? '/', '/');

        return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').$path;
    }
}
