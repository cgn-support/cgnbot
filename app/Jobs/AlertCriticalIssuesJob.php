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

    public function handle(): void
    {
        $settings = ClientSettings::for($this->client);

        $issues = CrawlIssue::where('crawl_run_id', $this->crawlRun->id)
            ->whereIn('severity', $settings['alert_on_severity'])
            ->whereNull('alerted_at')
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

            return "{$emoji} *{$issue->issue_type}* — `{$issue->url}`";
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
                        'text' => "Crawl run #{$this->crawlRun->id} — ".$this->crawlRun->started_at?->format('M j, Y g:i a'),
                    ],
                ],
            ],
        ];

        $payload = ['blocks' => $blocks];

        if ($channel) {
            $payload['channel'] = $channel;
        }

        Http::post($global->slack_webhook_url, $payload);

        $issues->each->update(['alerted_at' => now()]);
    }
}
