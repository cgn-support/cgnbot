<?php

use App\Filament\Widgets\LatestCrawlRunsWidget;
use App\Filament\Widgets\StatsOverviewWidget;

use function Pest\Livewire\livewire;

beforeEach(function () {
    loginAsAdmin();
});

it('renders the dashboard', function () {
    $this->get('/admin')
        ->assertSuccessful();
});

it('loads the stats overview widget', function () {
    livewire(StatsOverviewWidget::class)
        ->assertSuccessful();
});

it('loads the latest crawl runs widget', function () {
    livewire(LatestCrawlRunsWidget::class)
        ->assertSuccessful();
});
