<?php

namespace App\Jobs;

use App\Analyzers\IssueAnalyzer;
use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeCrawlRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public CrawlRun $crawlRun,
        public Client $client,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        $currentPages = CrawledPage::where('crawl_run_id', $this->crawlRun->id)->get();

        $previousRun = CrawlRun::where('client_id', $this->client->id)
            ->where('status', 'completed')
            ->where('id', '!=', $this->crawlRun->id)
            ->latest()
            ->first();

        $previousPages = $previousRun
            ? CrawledPage::where('crawl_run_id', $previousRun->id)->get()
            : collect();

        $settings = ClientSettings::for($this->client);

        $analyzer = new IssueAnalyzer;
        $newIssues = $analyzer->run($this->crawlRun, $this->client, $currentPages, $previousPages, $settings);

        if ($newIssues->isNotEmpty()) {
            CrawlIssue::insert($newIssues->toArray());
        }

        $this->autoResolveStaleIssues($currentPages, $newIssues);

        $criticalCount = $newIssues->where('severity', 'critical')->count();
        $warningCount = $newIssues->where('severity', 'warning')->count();
        $infoCount = $newIssues->where('severity', 'info')->count();

        $pagesWithIssues = $newIssues->pluck('url')->unique()->count();

        $this->crawlRun->update([
            'critical_issues_found' => $criticalCount,
            'warning_issues_found' => $warningCount,
            'info_issues_found' => $infoCount,
            'pages_with_issues' => $pagesWithIssues,
        ]);

        AlertCriticalIssuesJob::dispatch($this->crawlRun, $this->client);
    }

    private function autoResolveStaleIssues($currentPages, $newIssues): void
    {
        $currentUrls = $currentPages->pluck('url')->map(fn ($url) => rtrim($url, '/'))->toArray();
        $newIssueKeys = $newIssues->map(fn ($issue) => $issue['url'].'|'.$issue['issue_type'])->toArray();

        CrawlIssue::where('client_id', $this->client->id)
            ->whereNull('resolved_at')
            ->where('crawl_run_id', '!=', $this->crawlRun->id)
            ->get()
            ->each(function ($issue) use ($newIssueKeys) {
                $issueKey = $issue->url.'|'.$issue->issue_type;

                if (! in_array($issueKey, $newIssueKeys)) {
                    $issue->markResolved();
                }
            });
    }
}
