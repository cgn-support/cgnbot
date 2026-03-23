<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Support\Carbon;

class HealthReportService
{
    /**
     * @return array{
     *     client: array{name: string, domain: string},
     *     period: array{start: string, end: string, days: int},
     *     summary: array{
     *         total_crawls: int,
     *         successful_crawls: int,
     *         failed_crawls: int,
     *         total_pages_crawled: int,
     *         current_open_issues: array{critical: int, warning: int, info: int},
     *         issues_detected: int,
     *         issues_resolved: int,
     *     },
     *     crawl_history: array<int, array{date: string, status: string, pages: int, critical: int, warnings: int}>,
     *     top_issues: array<int, array{type: string, count: int, severity: string}>,
     *     open_issues: array<int, array{url: string, type: string, severity: string, first_detected: string, consecutive: int}>,
     * }
     */
    public function generate(Client $client, int $periodDays = 30): array
    {
        $periodEnd = now();
        $periodStart = now()->subDays($periodDays);

        return [
            'client' => $this->buildClientSection($client),
            'period' => $this->buildPeriodSection($periodStart, $periodEnd, $periodDays),
            'summary' => $this->buildSummarySection($client, $periodStart, $periodEnd),
            'crawl_history' => $this->buildCrawlHistory($client, $periodStart, $periodEnd),
            'top_issues' => $this->buildTopIssues($client, $periodStart, $periodEnd),
            'open_issues' => $this->buildOpenIssues($client),
        ];
    }

    /** @return array{name: string, domain: string} */
    private function buildClientSection(Client $client): array
    {
        return [
            'name' => $client->name,
            'domain' => $client->domain,
        ];
    }

    /** @return array{start: string, end: string, days: int} */
    private function buildPeriodSection(Carbon $start, Carbon $end, int $days): array
    {
        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => $days,
        ];
    }

    /**
     * @return array{
     *     total_crawls: int,
     *     successful_crawls: int,
     *     failed_crawls: int,
     *     total_pages_crawled: int,
     *     current_open_issues: array{critical: int, warning: int, info: int},
     *     issues_detected: int,
     *     issues_resolved: int,
     * }
     */
    private function buildSummarySection(Client $client, Carbon $periodStart, Carbon $periodEnd): array
    {
        $crawlRuns = $client->crawlRuns()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        $openIssues = CrawlIssue::where('client_id', $client->id)->whereNull('resolved_at')->get();

        return [
            'total_crawls' => $crawlRuns->count(),
            'successful_crawls' => $crawlRuns->where('status', 'completed')->count(),
            'failed_crawls' => $crawlRuns->where('status', 'failed')->count(),
            'total_pages_crawled' => (int) $crawlRuns->sum('pages_crawled'),
            'current_open_issues' => [
                'critical' => $openIssues->where('severity', 'critical')->count(),
                'warning' => $openIssues->where('severity', 'warning')->count(),
                'info' => $openIssues->where('severity', 'info')->count(),
            ],
            'issues_detected' => CrawlIssue::where('client_id', $client->id)
                ->whereBetween('detected_at', [$periodStart, $periodEnd])
                ->count(),
            'issues_resolved' => CrawlIssue::where('client_id', $client->id)
                ->whereBetween('resolved_at', [$periodStart, $periodEnd])
                ->count(),
        ];
    }

    /** @return array<int, array{date: string, status: string, pages: int, critical: int, warnings: int}> */
    private function buildCrawlHistory(Client $client, Carbon $periodStart, Carbon $periodEnd): array
    {
        $runs = CrawlRun::where('client_id', $client->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->get();

        return $runs->map(fn (CrawlRun $run) => [
            'date' => $run->created_at instanceof \DateTimeInterface ? $run->created_at->format('Y-m-d H:i:s') : (string) $run->created_at,
            'status' => $run->status,
            'pages' => (int) $run->pages_crawled,
            'critical' => (int) $run->critical_issues_found,
            'warnings' => (int) $run->warning_issues_found,
        ])->values()->all();
    }

    /** @return array<int, array{type: string, count: int, severity: string}> */
    private function buildTopIssues(Client $client, Carbon $periodStart, Carbon $periodEnd): array
    {
        $results = CrawlIssue::where('client_id', $client->id)
            ->whereBetween('detected_at', [$periodStart, $periodEnd])
            ->selectRaw('issue_type, severity, COUNT(*) as issue_count')
            ->groupBy('issue_type', 'severity')
            ->orderByDesc('issue_count')
            ->limit(10)
            ->get();

        return $results->map(fn (CrawlIssue $issue) => [
            'type' => $issue->issue_type,
            'count' => (int) $issue->getAttribute('issue_count'),
            'severity' => $issue->severity,
        ])->all();
    }

    /** @return array<int, array{url: string, type: string, severity: string, first_detected: string, consecutive: int}> */
    private function buildOpenIssues(Client $client): array
    {
        $severityOrder = ['critical' => 1, 'warning' => 2, 'info' => 3];

        $issues = CrawlIssue::where('client_id', $client->id)
            ->whereNull('resolved_at')
            ->orderByDesc('consecutive_detections')
            ->get();

        return $issues
            ->sortBy(fn (CrawlIssue $issue) => $severityOrder[$issue->severity] ?? 4)
            ->map(fn (CrawlIssue $issue) => [
                'url' => $issue->url,
                'type' => $issue->issue_type,
                'severity' => $issue->severity,
                'first_detected' => $issue->detected_at instanceof \DateTimeInterface ? $issue->detected_at->format('Y-m-d H:i:s') : 'unknown',
                'consecutive' => (int) $issue->consecutive_detections,
            ])
            ->values()
            ->all();
    }
}
