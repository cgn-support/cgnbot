<?php

namespace App\Jobs;

use App\Analyzers\IssueAnalyzer;
use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use App\Services\CrawlHealthGate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $healthGate = new CrawlHealthGate;
        $healthResult = $healthGate->evaluate($this->crawlRun);

        if (! $healthResult->healthy) {
            Log::warning("Crawl health gate failed for client {$this->client->id}: {$healthResult->reason}");

            $this->crawlRun->update([
                'context' => array_merge($this->crawlRun->context ?? [], [
                    'health_gate_failed' => true,
                    'health_gate_reason' => $healthResult->reason,
                ]),
            ]);

            $this->runLimitedAnalysis($currentPages);

            return;
        }

        $this->runFullAnalysis($currentPages);
    }

    private function runLimitedAnalysis($currentPages): void
    {
        $settings = ClientSettings::for($this->client);

        $analyzer = new IssueAnalyzer;
        $newIssues = $analyzer->runChecks(
            $this->crawlRun,
            $this->client,
            $currentPages,
            collect(),
            $settings,
            ['\App\Analyzers\Checks\HomepageDownCheck'],
        );

        $this->upsertIssues($newIssues, $currentPages);
        $this->updateRunCounts($newIssues);
    }

    private function runFullAnalysis($currentPages): void
    {
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

        $this->upsertIssues($newIssues, $currentPages);
        $this->updateRunCounts($newIssues);

        Bus::chain([
            new VerifyLowConfidenceIssuesJob($this->crawlRun, $this->client),
            new AlertCriticalIssuesJob($this->crawlRun, $this->client),
        ])->dispatch();
    }

    private function upsertIssues($newIssues, $currentPages): void
    {
        $crawledUrls = $currentPages->pluck('url')
            ->map(fn ($url) => rtrim($url, '/'))
            ->unique()
            ->all();

        DB::transaction(function () use ($newIssues, $crawledUrls) {
            $existingIssues = CrawlIssue::where('client_id', $this->client->id)
                ->whereNull('resolved_at')
                ->get()
                ->keyBy(fn ($issue) => $issue->issueKey());

            $newIssueKeys = [];

            foreach ($newIssues as $issueData) {
                $key = $issueData['url'].'|'.$issueData['issue_type'];
                $newIssueKeys[] = $key;

                if ($existingIssues->has($key)) {
                    $existing = $existingIssues->get($key);
                    $existing->update([
                        'crawl_run_id' => $this->crawlRun->id,
                        'consecutive_detections' => $existing->consecutive_detections + 1,
                        'context' => $issueData['context'],
                        'confidence' => $issueData['confidence'] ?? $existing->confidence,
                        'updated_at' => now(),
                    ]);
                } else {
                    CrawlIssue::create(array_merge($issueData, [
                        'consecutive_detections' => 1,
                        'first_detected_run_id' => $this->crawlRun->id,
                    ]));
                }
            }

            // Only auto-resolve issues on pages that were actually crawled in this run
            $existingIssues->each(function ($issue) use ($newIssueKeys, $crawledUrls) {
                if (in_array($issue->issueKey(), $newIssueKeys)) {
                    return;
                }

                $issueUrlNormalized = rtrim($issue->url, '/');
                if (in_array($issueUrlNormalized, $crawledUrls)) {
                    $issue->markResolved();
                }
            });
        });
    }

    private function updateRunCounts($newIssues): void
    {
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
    }
}
