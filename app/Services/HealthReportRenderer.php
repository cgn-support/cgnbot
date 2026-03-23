<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class HealthReportRenderer
{
    /**
     * @param array{
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
     * } $reportData
     */
    public function toMarkdown(array $reportData): string
    {
        $lines = [];

        $lines[] = $this->renderHeader($reportData);
        $lines[] = $this->renderSummary($reportData['summary']);
        $lines[] = $this->renderCrawlHistory($reportData['crawl_history']);
        $lines[] = $this->renderTopIssues($reportData['top_issues']);
        $lines[] = $this->renderOpenIssues($reportData['open_issues']);

        return implode("\n", $lines);
    }

    private function renderHeader(array $reportData): string
    {
        $clientName = $reportData['client']['name'];
        $domain = $reportData['client']['domain'];
        $periodStart = Carbon::parse($reportData['period']['start'])->format('M j, Y');
        $periodEnd = Carbon::parse($reportData['period']['end'])->format('M j, Y');

        return <<<MARKDOWN
        # SEO Health Report: {$clientName}
        **Domain:** {$domain}
        **Period:** {$periodStart} - {$periodEnd}

        MARKDOWN;
    }

    /**
     * @param array{
     *     total_crawls: int,
     *     successful_crawls: int,
     *     failed_crawls: int,
     *     total_pages_crawled: int,
     *     current_open_issues: array{critical: int, warning: int, info: int},
     *     issues_detected: int,
     *     issues_resolved: int,
     * } $summary
     */
    private function renderSummary(array $summary): string
    {
        $open = $summary['current_open_issues'];

        return <<<MARKDOWN
        ## Summary
        - Total Crawls: {$summary['total_crawls']} ({$summary['successful_crawls']} successful, {$summary['failed_crawls']} failed)
        - Pages Crawled: {$summary['total_pages_crawled']}
        - Open Issues: {$open['critical']} critical, {$open['warning']} warnings, {$open['info']} info
        - Issues Detected: {$summary['issues_detected']} | Resolved: {$summary['issues_resolved']}

        MARKDOWN;
    }

    /**
     * @param  array<int, array{date: string, status: string, pages: int, critical: int, warnings: int}>  $crawlHistory
     */
    private function renderCrawlHistory(array $crawlHistory): string
    {
        if ($crawlHistory === []) {
            return "## Crawl History\nNo crawls recorded in this period.\n";
        }

        $lines = [
            '## Crawl History',
            '| Date | Status | Pages | Critical | Warnings |',
            '|------|--------|-------|----------|----------|',
        ];

        foreach ($crawlHistory as $crawl) {
            $date = Carbon::parse($crawl['date'])->format('M j, Y H:i');
            $lines[] = "| {$date} | {$crawl['status']} | {$crawl['pages']} | {$crawl['critical']} | {$crawl['warnings']} |";
        }

        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, array{type: string, count: int, severity: string}>  $topIssues
     */
    private function renderTopIssues(array $topIssues): string
    {
        if ($topIssues === []) {
            return "## Top Issue Types\nNo issues detected in this period.\n";
        }

        $lines = [
            '## Top Issue Types',
            '| Type | Count | Severity |',
            '|------|-------|----------|',
        ];

        foreach ($topIssues as $issue) {
            $lines[] = "| {$issue['type']} | {$issue['count']} | {$issue['severity']} |";
        }

        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, array{url: string, type: string, severity: string, first_detected: string, consecutive: int}>  $openIssues
     */
    private function renderOpenIssues(array $openIssues): string
    {
        if ($openIssues === []) {
            return "## Open Issues\nNo open issues. All clear!\n";
        }

        $lines = ['## Open Issues'];
        $currentSeverity = '';

        foreach ($openIssues as $issue) {
            if ($issue['severity'] !== $currentSeverity) {
                $currentSeverity = $issue['severity'];
                $lines[] = '';
                $lines[] = '### '.ucfirst($currentSeverity);
            }

            $consecutiveNote = $issue['consecutive'] > 1
                ? " (detected {$issue['consecutive']} consecutive crawls)"
                : '';

            $lines[] = "- {$issue['url']} - {$issue['type']}{$consecutiveNote}";
        }

        $lines[] = '';

        return implode("\n", $lines)."\n";
    }
}
