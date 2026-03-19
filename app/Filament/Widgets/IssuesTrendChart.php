<?php

namespace App\Filament\Widgets;

use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class IssuesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Issues & Crawl Runs (14 days)';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $issueData = $days->map(fn ($day) => CrawlIssue::whereDate('detected_at', $day)->count());

        $crawlData = $days->map(fn ($day) => CrawlRun::where('status', 'completed')->whereDate('started_at', $day)->count());

        return [
            'datasets' => [
                [
                    'label' => 'Issues Detected',
                    'data' => $issueData->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
                [
                    'label' => 'Crawl Runs',
                    'data' => $crawlData->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $days->map(fn ($day) => $day->format('M j'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
