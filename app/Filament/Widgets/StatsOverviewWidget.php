<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\CrawlIssue;
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
        ];
    }
}
