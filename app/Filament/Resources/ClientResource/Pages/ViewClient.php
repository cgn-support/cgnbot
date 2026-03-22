<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Jobs\CrawlClientJob;
use App\Models\Client;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Overview')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('domain')
                            ->url(fn (Client $record) => $record->domain, true),
                        TextEntry::make('last_crawled_at')
                            ->since(),
                        IconEntry::make('is_active')
                            ->boolean(),
                        TextEntry::make('open_critical_count')
                            ->label('Critical Issues')
                            ->state(fn (Client $record) => $record->openCriticalIssuesCount())
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('crawl_now')
                ->label('Crawl Now')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    /** @var Client $client */
                    $client = $this->record;
                    CrawlClientJob::dispatch($client, triggeredManually: true);
                    Notification::make()->title('Crawl dispatched')->success()->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
