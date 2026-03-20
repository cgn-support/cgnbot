<?php

use App\Filament\Resources\CrawlRunResource;
use App\Filament\Resources\CrawlRunResource\Pages\ListCrawlRuns;
use App\Models\CrawlRun;

use function Pest\Livewire\livewire;

beforeEach(function () {
    loginAsAdmin();
});

it('renders the list page', function () {
    $this->get(CrawlRunResource::getUrl('index'))
        ->assertSuccessful();
});

it('lists crawl runs in the table', function () {
    $runs = CrawlRun::factory()->count(3)->create();

    livewire(ListCrawlRuns::class)
        ->assertCanSeeTableRecords($runs);
});

it('cannot create crawl runs directly', function () {
    expect(CrawlRunResource::canCreate())->toBeFalse();
});

it('can filter by status', function () {
    $completed = CrawlRun::factory()->create(['status' => 'completed']);
    $failed = CrawlRun::factory()->failed()->create();

    livewire(ListCrawlRuns::class)
        ->filterTable('status', 'completed')
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$failed]);
});
