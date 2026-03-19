<?php

namespace App\Filament\Widgets;

use App\Models\CrawlRun;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestCrawlRunsWidget extends BaseWidget
{
    protected static ?string $heading = 'Latest Crawl Runs';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(CrawlRun::query()->with('client')->latest('started_at')->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('client.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'running' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pages_crawled'),
                Tables\Columns\TextColumn::make('critical_issues_found')
                    ->label('Critical')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('started_at')
                    ->since(),
            ])
            ->paginated(false);
    }
}
