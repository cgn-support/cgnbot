<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrawlIssueResource\Pages;
use App\Models\CrawlIssue;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class CrawlIssueResource extends Resource
{
    protected static ?string $model = CrawlIssue::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Crawl Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Issues';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('crawl_issues_critical_count', 300, function () {
            return CrawlIssue::open()->critical()->count();
        });

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issue_type')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('url')
                    ->limit(60)
                    ->tooltip(fn (CrawlIssue $record) => $record->url),
                Tables\Columns\TextColumn::make('detected_at')
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('resolved_at')
                    ->label('Resolved')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn (CrawlIssue $record) => $record->resolved_at !== null),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'critical' => 'Critical',
                        'warning' => 'Warning',
                        'info' => 'Info',
                    ]),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name'),
                Tables\Filters\SelectFilter::make('issue_type')
                    ->options(fn () => CrawlIssue::distinct()->pluck('issue_type', 'issue_type')->toArray()),
                Tables\Filters\Filter::make('open_only')
                    ->label('Open only')
                    ->query(fn ($query) => $query->whereNull('resolved_at'))
                    ->default(),
            ])
            ->actions([
                Action::make('mark_resolved')
                    ->label('Mark Resolved')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CrawlIssue $record) => $record->resolved_at === null)
                    ->action(fn (CrawlIssue $record) => $record->markResolved()),
            ])
            ->bulkActions([
                BulkAction::make('bulk_resolve')
                    ->label('Mark Resolved')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->markResolved())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrawlIssues::route('/'),
        ];
    }
}
