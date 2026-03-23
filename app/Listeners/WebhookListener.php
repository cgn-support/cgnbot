<?php

namespace App\Listeners;

use App\Events\CrawlCompleted;
use App\Events\CrawlFailed;
use App\Events\CriticalIssueDetected;
use App\Models\CrawlerSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookListener
{
    public function handleCrawlCompleted(CrawlCompleted $event): void
    {
        $this->sendWebhook('crawl.completed', [
            'client' => [
                'id' => $event->client->id,
                'name' => $event->client->name,
                'domain' => $event->client->domain,
            ],
            'crawl_run' => [
                'id' => $event->crawlRun->id,
                'pages_crawled' => $event->crawlRun->pages_crawled,
                'critical_issues_found' => $event->crawlRun->critical_issues_found,
                'warning_issues_found' => $event->crawlRun->warning_issues_found,
                'duration_seconds' => $event->crawlRun->durationSeconds(),
            ],
        ]);
    }

    public function handleCrawlFailed(CrawlFailed $event): void
    {
        $this->sendWebhook('crawl.failed', [
            'client' => [
                'id' => $event->client->id,
                'name' => $event->client->name,
                'domain' => $event->client->domain,
            ],
            'crawl_run' => [
                'id' => $event->crawlRun->id,
                'error_message' => $event->errorMessage,
            ],
        ]);
    }

    public function handleCriticalIssueDetected(CriticalIssueDetected $event): void
    {
        $this->sendWebhook('critical_issue.detected', [
            'client' => [
                'id' => $event->client->id,
                'name' => $event->client->name,
                'domain' => $event->client->domain,
            ],
            'crawl_run' => [
                'id' => $event->crawlRun->id,
            ],
            'issue' => [
                'id' => $event->issue->id,
                'url' => $event->issue->url,
                'issue_type' => $event->issue->issue_type,
                'severity' => $event->issue->severity,
                'context' => $event->issue->context,
            ],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function sendWebhook(string $eventName, array $data): void
    {
        $webhookUrl = CrawlerSetting::current()->webhook_url;

        if (! $webhookUrl) {
            return;
        }

        $payload = [
            'event' => $eventName,
            ...$data,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Throwable $exception) {
            Log::warning("Webhook delivery failed for {$eventName}", [
                'url' => $webhookUrl,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            CrawlCompleted::class => 'handleCrawlCompleted',
            CrawlFailed::class => 'handleCrawlFailed',
            CriticalIssueDetected::class => 'handleCriticalIssueDetected',
        ];
    }
}
