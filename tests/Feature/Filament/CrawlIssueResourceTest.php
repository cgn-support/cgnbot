<?php

use App\Filament\Resources\CrawlIssueResource;
use App\Filament\Resources\CrawlIssueResource\Pages\ListCrawlIssues;
use App\Models\CrawlIssue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    loginAsAdmin();
});

it('renders the list page', function () {
    $this->get(CrawlIssueResource::getUrl('index'))
        ->assertSuccessful();
});

it('lists issues in the table', function () {
    $issues = CrawlIssue::factory()->count(3)->create();

    livewire(ListCrawlIssues::class)
        ->assertCanSeeTableRecords($issues);
});

it('can mark an issue as resolved', function () {
    $issue = CrawlIssue::factory()->create();

    livewire(ListCrawlIssues::class)
        ->callTableAction('mark_resolved', $issue);

    expect($issue->fresh()->resolved_at)->not->toBeNull();
});

it('can filter by severity', function () {
    $critical = CrawlIssue::factory()->critical()->create();
    $info = CrawlIssue::factory()->info()->create();

    livewire(ListCrawlIssues::class)
        ->filterTable('severity', 'critical')
        ->assertCanSeeTableRecords([$critical])
        ->assertCanNotSeeTableRecords([$info]);
});

it('cannot create issues directly', function () {
    expect(CrawlIssueResource::canCreate())->toBeFalse();
});
