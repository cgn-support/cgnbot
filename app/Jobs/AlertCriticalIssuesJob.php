<?php

namespace App\Jobs;

use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertCriticalIssuesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public CrawlRun $crawlRun,
        public Client $client,
    ) {
        $this->queue = 'default';
    }

    /**
     * Alert threshold rules:
     * 1. Recurring issues: detected >= N consecutive times AND confidence >= M% (configurable)
     * 2. New critical issues: severity=critical AND confidence >= 80% (immediate alert)
     *
     * Issues already alerted within the last 24 hours are skipped to prevent Slack noise.
     */
    public function handle(): void
    {
        $settings = ClientSettings::for($this->client);

        $minConsecutive = $settings['alert_min_consecutive_detections'] ?? 2;
        $minConfidence = $settings['alert_min_confidence'] ?? 70;

        $issues = CrawlIssue::where('crawl_run_id', $this->crawlRun->id)
            ->whereIn('severity', $settings['alert_on_severity'])
            ->where(function ($query) {
                // Not yet alerted, or last alerted more than 24 hours ago
                $query->whereNull('alerted_at')
                    ->orWhere('alerted_at', '<', now()->subHours(24));
            })
            ->where(function ($query) use ($minConsecutive, $minConfidence) {
                $query->where(function ($q) use ($minConsecutive, $minConfidence) {
                    $q->where('consecutive_detections', '>=', $minConsecutive)
                        ->where('confidence', '>=', $minConfidence);
                })->orWhere(function ($q) {
                    $q->where('severity', 'critical')
                        ->where('confidence', '>=', 80);
                });
            })
            ->get();

        if ($issues->isEmpty()) {
            return;
        }

        $global = CrawlerSetting::current();

        if (empty($global->slack_webhook_url)) {
            return;
        }

        $channel = $this->client->slack_channel ?? $global->slack_default_channel;

        $issueLines = $issues->map(function ($issue) {
            $emoji = $issue->severity === 'critical' ? ':red_circle:' : ':warning:';
            $confidence = "({$issue->confidence}% confidence)";

            return "{$emoji} *{$issue->issue_type}* — `{$issue->url}` {$confidence}";
        })->join("\n");

        $domain = $this->client->resolvedDomain();
        $count = $issues->count();
        $severityLabel = $count === 1 ? 'issue' : 'issues';

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "🚨 Critical SEO Issues: {$this->client->name}",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*{$count} {$severityLabel} detected* on <{$domain}|{$domain}>\n\n{$issueLines}",
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Crawl run #{$this->crawlRun->id} — ".($this->crawlRun->started_at ? $this->crawlRun->started_at->format('M j, Y g:i a') : 'unknown'),
                    ],
                ],
            ],
        ];

        $payload = ['blocks' => $blocks];

        if ($channel) {
            $payload['channel'] = $channel;
        }

        try {
            $response = Http::retry(3, 100)->post($global->slack_webhook_url, $payload);

            if ($response->successful()) {
                $issues->each->update(['alerted_at' => now()]);
            } else {
                Log::error("Slack webhook returned {$response->status()} for client {$this->client->name}");
            }
        } catch (\Throwable $e) {
            Log::error("Slack webhook failed for client {$this->client->name}: {$e->getMessage()}");
        }
    }
}
