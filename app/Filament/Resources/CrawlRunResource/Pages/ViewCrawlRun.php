<?php

namespace App\Filament\Resources\CrawlRunResource\Pages;

use App\Filament\Resources\CrawlRunResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCrawlRun extends ViewRecord
{
    protected static string $resource = CrawlRunResource::class;

    public function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Run Details')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('client.name'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'running' => 'warning',
                                default => 'gray',
                            }),
                        IconEntry::make('triggered_manually')
                            ->label('Manual')
                            ->boolean(),
                        TextEntry::make('started_at')
                            ->dateTime(),
                    ]),

                Section::make('Results')
                    ->columns(5)
                    ->schema([
                        TextEntry::make('pages_crawled'),
                        TextEntry::make('pages_with_issues'),
                        TextEntry::make('critical_issues_found')
                            ->label('Critical')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                        TextEntry::make('warning_issues_found')
                            ->label('Warnings')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                        TextEntry::make('info_issues_found')
                            ->label('Info')
                            ->badge()
                            ->color('gray'),
                    ]),

                Section::make('Timing')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('started_at')
                            ->label('Started')
                            ->dateTime(),
                        TextEntry::make('finished_at')
                            ->label('Finished')
                            ->dateTime(),
                        TextEntry::make('duration')
                            ->label('Duration')
                            ->state(fn ($record) => $record->durationSeconds() !== null ? $record->durationSeconds().'s' : 'N/A'),
                    ]),

                Section::make('Error')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->error_message !== null),
            ]);
    }
}
