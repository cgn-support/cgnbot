<?php

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Jobs\CrawlClientJob;
use App\Models\Client;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    loginAsAdmin();
});

it('renders the list page', function () {
    $this->get(ClientResource::getUrl('index'))
        ->assertSuccessful();
});

it('lists clients in the table', function () {
    $clients = Client::factory()->count(3)->create();

    livewire(ListClients::class)
        ->assertCanSeeTableRecords($clients);
});

it('renders the create form', function () {
    $this->get(ClientResource::getUrl('create'))
        ->assertSuccessful();
});

it('can create a client', function () {
    livewire(CreateClient::class)
        ->set('data.name', 'Acme Remodeling')
        ->set('data.slug', 'acme-remodeling')
        ->set('data.domain', 'https://acmeremodeling.com')
        ->set('data.is_active', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('clients', [
        'slug' => 'acme-remodeling',
    ]);
});

it('renders the edit form', function () {
    $client = Client::factory()->create();

    $this->get(ClientResource::getUrl('edit', ['record' => $client]))
        ->assertSuccessful();
});

it('can update a client', function () {
    $client = Client::factory()->create();

    livewire(EditClient::class, ['record' => $client->getRouteKey()])
        ->set('data.name', 'Updated Name')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->fresh()->name)->toBe('Updated Name');
});

it('shows expected table columns', function () {
    Client::factory()->create();

    livewire(ListClients::class)
        ->assertCanRenderTableColumn('name')
        ->assertCanRenderTableColumn('domain')
        ->assertCanRenderTableColumn('is_active')
        ->assertCanRenderTableColumn('last_crawled_at');
});

it('dispatches crawl job via crawl now action', function () {
    Queue::fake();

    $client = Client::factory()->create();

    livewire(ListClients::class)
        ->callTableAction('crawl_now', $client);

    Queue::assertPushed(CrawlClientJob::class, function ($job) use ($client) {
        return $job->client->id === $client->id;
    });
});
