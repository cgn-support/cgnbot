<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $activeClients = Client::active()->count();
        $openCritical = CrawlIssue::open()->critical()->count();
        $openWarnings = CrawlIssue::open()->where('severity', 'warning')->count();
        $resolvedToday = CrawlIssue::whereNotNull('resolved_at')
            ->whereDate('resolved_at', today())
            ->count();

        $staleClients = Client::active()
            ->where(function ($q) {
                $q->where('last_crawled_at', '<', now()->subHours(48))
                    ->orWhereNull('last_crawled_at');
            })
            ->count();

        $failedRecent = CrawlRun::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [
            Stat::make('Active Clients', $activeClients)
                ->icon('heroicon-o-building-office'),
            Stat::make('Open Critical', $openCritical)
                ->color($openCritical > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Open Warnings', $openWarnings)
                ->color($openWarnings > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Resolved Today', $resolvedToday)
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Stale Clients (48h+)', $staleClients)
                ->color($staleClients > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock')
                ->description('Not crawled in 48+ hours'),
            Stat::make('Failed Crawls (24h)', $failedRecent)
                ->color($failedRecent > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
