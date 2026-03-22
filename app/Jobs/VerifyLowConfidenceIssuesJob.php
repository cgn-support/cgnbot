<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use App\Services\BrowsershotVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyLowConfidenceIssuesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    private const MAX_VERIFICATIONS_PER_RUN = 25;

    private const VERIFIABLE_CHECKS = [
        'MissingH1Check',
        'MultipleH1Check',
        'MissingTitleCheck',
        'MissingMetaDescriptionCheck',
        'ThinContentCheck',
    ];

    public function __construct(
        public CrawlRun $crawlRun,
        public Client $client,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        $issues = CrawlIssue::where('crawl_run_id', $this->crawlRun->id)
            ->where('confidence', '<', 80)
            ->whereNull('verified_at')
            ->whereNull('resolved_at')
            ->whereIn('issue_type', self::VERIFIABLE_CHECKS)
            ->get();

        if ($issues->isEmpty()) {
            return;
        }

        $grouped = $issues->groupBy('url');
        $verifier = new BrowsershotVerifier;
        $verified = 0;

        foreach ($grouped as $url => $urlIssues) {
            if ($verified >= self::MAX_VERIFICATIONS_PER_RUN) {
                break;
            }

            $signals = $verifier->verify($url);

            if ($signals === null) {
                Log::warning("Browsershot verification failed for {$url}, skipping");

                continue;
            }

            foreach ($urlIssues as $issue) {
                $this->resolveOrConfirm($issue, $signals);
            }

            $verified++;
        }
    }

    private function resolveOrConfirm(CrawlIssue $issue, array $signals): void
    {
        $isFalsePositive = match ($issue->issue_type) {
            'MissingH1Check' => $signals['h1_count'] > 0,
            'MultipleH1Check' => $signals['h1_count'] <= 1,
            'MissingTitleCheck' => ! empty($signals['meta_title']),
            'MissingMetaDescriptionCheck' => ! empty($signals['meta_description']),
            'ThinContentCheck' => $signals['word_count'] >= ($issue->context['threshold'] ?? 300),
            default => false,
        };

        if ($isFalsePositive) {
            $issue->update([
                'resolved_at' => now(),
                'verified_at' => now(),
                'verified_by' => 'browsershot',
                'context' => array_merge($issue->context ?? [], [
                    'verification' => 'false_positive',
                    'rendered_signals' => $signals,
                ]),
            ]);

            Log::info("False positive resolved: {$issue->issue_type} on {$issue->url}");
        } else {
            $issue->update([
                'confidence' => 100,
                'verified_at' => now(),
                'verified_by' => 'browsershot',
                'context' => array_merge($issue->context ?? [], [
                    'verification' => 'confirmed',
                    'rendered_signals' => $signals,
                ]),
            ]);
        }
    }
}
