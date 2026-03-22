<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Jobs\CrawlClientJob;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Schemas\Components\Section::make('Client Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('URL-safe identifier'),
                        Forms\Components\TextInput::make('domain')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                        Forms\Components\TextInput::make('slack_channel')
                            ->placeholder('#client-alerts'),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),

                Schemas\Components\Section::make('Crawl Settings')
                    ->description('Leave blank to use global defaults')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('settings.crawl_frequency_hours')
                            ->numeric()
                            ->label('Crawl Frequency (hours)'),
                        Forms\Components\TextInput::make('settings.max_depth')
                            ->numeric()
                            ->label('Max Depth'),
                        Forms\Components\TextInput::make('settings.crawl_limit')
                            ->numeric()
                            ->label('Crawl Limit'),
                        Forms\Components\TextInput::make('settings.concurrency')
                            ->numeric()
                            ->label('Concurrency'),
                        Forms\Components\TextInput::make('settings.slow_response_threshold_ms')
                            ->numeric()
                            ->label('Slow Response Threshold (ms)'),
                        Forms\Components\TextInput::make('settings.thin_content_threshold')
                            ->numeric()
                            ->label('Thin Content Threshold'),
                    ]),

                Schemas\Components\Section::make('Monitored URLs')
                    ->schema([
                        Forms\Components\Repeater::make('settings.monitored_urls')
                            ->simple(
                                Forms\Components\TextInput::make('url')
                                    ->placeholder('/services'),
                            )
                            ->addActionLabel('Add URL')
                            ->defaultItems(0),
                    ]),

                Schemas\Components\Section::make('Excluded URL Patterns')
                    ->schema([
                        Forms\Components\Repeater::make('settings.excluded_patterns')
                            ->simple(
                                Forms\Components\TextInput::make('pattern')
                                    ->placeholder('/wp-admin'),
                            )
                            ->addActionLabel('Add pattern')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('domain')
                    ->url(fn (Client $record) => $record->domain, true)
                    ->limit(40),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('open_issues_count')
                    ->counts('openIssues')
                    ->label('Open Issues')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'danger',
                        $state >= 1 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('last_crawled_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Action::make('crawl_now')
                    ->label('Crawl Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Client $record) {
                        CrawlClientJob::dispatch($record, triggeredManually: true);
                        Notification::make()->title('Crawl dispatched')->success()->send();
                    }),
                EditAction::make(),
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('crawl_selected')
                    ->label('Crawl Selected')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each(fn (Client $client) => CrawlClientJob::dispatch($client, triggeredManually: true));
                        Notification::make()->title("Crawl dispatched for {$records->count()} client(s)")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'view' => Pages\ViewClient::route('/{record}'),
        ];
    }
}
