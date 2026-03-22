<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrawlRunResource\Pages;
use App\Jobs\CrawlClientJob;
use App\Models\Client;
use App\Models\CrawlRun;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CrawlRunResource extends Resource
{
    protected static ?string $model = CrawlRun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Crawl Data';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'running' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('triggered_manually')
                    ->label('Manual')
                    ->boolean(),
                Tables\Columns\TextColumn::make('pages_crawled'),
                Tables\Columns\TextColumn::make('critical_issues_found')
                    ->label('Critical')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('warning_issues_found')
                    ->label('Warnings')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('started_at')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->state(fn (CrawlRun $record) => $record->durationSeconds() !== null ? $record->durationSeconds().'s' : ''),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CrawlRun $record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (CrawlRun $record) {
                        /** @var Client $client */
                        $client = $record->client;
                        CrawlClientJob::dispatch($client, triggeredManually: true);
                        Notification::make()->title('Crawl re-queued for '.$client->name)->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrawlRuns::route('/'),
            'view' => Pages\ViewCrawlRun::route('/{record}'),
        ];
    }
}
