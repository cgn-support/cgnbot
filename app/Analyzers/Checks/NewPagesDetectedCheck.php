<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;

class NewPagesDetectedCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        if ($previousPages->isEmpty()) {
            return $issues;
        }

        $previousUrls = $previousPages->pluck('url')->map(fn ($url) => rtrim($url, '/'))->toArray();
        $previousUrlMap = array_flip($previousUrls);

        $currentPages
            ->filter(fn ($page) => $page->status_code === 200)
            ->filter(fn ($page) => ! isset($previousUrlMap[rtrim($page->url, '/')]))
            ->each(function ($page) use ($crawlRun, $client, $issues) {
                $issues->push($this->issue(
                    $crawlRun,
                    $client,
                    $page->url,
                    'NewPagesDetectedCheck',
                    'info',
                ));
            });

        return $issues;
    }
}
