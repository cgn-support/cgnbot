<?php

namespace App\Services;

use App\Models\CrawledPage;
use App\Models\CrawlRun;
use App\ValueObjects\CrawlHealthResult;

class CrawlHealthGate
{
    public function evaluate(CrawlRun $crawlRun): CrawlHealthResult
    {
        $previousRun = CrawlRun::where('client_id', $crawlRun->client_id)
            ->where('status', 'completed')
            ->where('id', '!=', $crawlRun->id)
            ->latest()
            ->first();

        if (! $previousRun) {
            return CrawlHealthResult::pass();
        }

        $currentPages = CrawledPage::where('crawl_run_id', $crawlRun->id)->count();
        $previousPageCount = $previousRun->pages_crawled;

        // Only apply percentage-based check for sites with meaningful page counts
        if ($previousPageCount >= 20 && $currentPages < ($previousPageCount * 0.5)) {
            return CrawlHealthResult::fail(
                "Page count dropped significantly: {$currentPages} vs previous {$previousPageCount}"
            );
        }

        $totalPages = $currentPages;
        $failedPages = CrawledPage::where('crawl_run_id', $crawlRun->id)
            ->where('status_code', 0)
            ->count();

        if ($totalPages > 0 && ($failedPages / $totalPages) > 0.3) {
            return CrawlHealthResult::fail(
                "High connection failure rate: {$failedPages}/{$totalPages} pages returned status 0"
            );
        }

        return CrawlHealthResult::pass();
    }
}
