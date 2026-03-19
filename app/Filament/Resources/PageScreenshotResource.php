<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageScreenshotResource\Pages;
use App\Models\PageScreenshot;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageScreenshotResource extends Resource
{
    protected static ?string $model = PageScreenshot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';

    protected static string|\UnitEnum|null $navigationGroup = 'Crawl Data';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Screenshots';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Screenshot')
                    ->disk(fn (PageScreenshot $record) => $record->disk)
                    ->height(60),
                Tables\Columns\TextColumn::make('url')
                    ->limit(50),
                Tables\Columns\TextColumn::make('diff_percentage')
                    ->label('Diff %')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state > 15 => 'danger',
                        (float) $state > 5 => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (?string $state) => $state !== null ? $state.'%' : 'N/A'),
                Tables\Columns\TextColumn::make('captured_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('captured_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPageScreenshots::route('/'),
        ];
    }
}
