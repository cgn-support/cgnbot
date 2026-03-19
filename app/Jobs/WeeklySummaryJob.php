<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class WeeklySummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        $global = CrawlerSetting::current();

        if (empty($global->slack_webhook_url)) {
            return;
        }

        $totalActive = Client::active()->count();
        $openCritical = CrawlIssue::open()->critical()->count();
        $openWarnings = CrawlIssue::open()->where('severity', 'warning')->count();
        $resolvedLastWeek = CrawlIssue::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subWeek())
            ->count();
        $clientsWithCritical = Client::active()
            ->whereHas('crawlIssues', fn ($q) => $q->open()->critical())
            ->count();
        $cleanClients = $totalActive - Client::active()
            ->whereHas('crawlIssues', fn ($q) => $q->open())
            ->count();

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '📊 Weekly SEO Watchdog Summary',
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Active Clients:*\n{$totalActive}"],
                    ['type' => 'mrkdwn', 'text' => "*Open Critical:*\n{$openCritical}"],
                    ['type' => 'mrkdwn', 'text' => "*Open Warnings:*\n{$openWarnings}"],
                    ['type' => 'mrkdwn', 'text' => "*Resolved (7d):*\n{$resolvedLastWeek}"],
                    ['type' => 'mrkdwn', 'text' => "*Clients w/ Critical:*\n{$clientsWithCritical}"],
                    ['type' => 'mrkdwn', 'text' => "*Clean Clients:*\n{$cleanClients}"],
                ],
            ],
        ];

        $payload = ['blocks' => $blocks];

        if ($global->slack_default_channel) {
            $payload['channel'] = $global->slack_default_channel;
        }

        Http::post($global->slack_webhook_url, $payload);
    }
}
