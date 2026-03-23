<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Http\Resources\CrawlIssueResource;
use App\Jobs\CrawlClientJob;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ClientsController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $clients = Client::active()
            ->with(['latestCrawlRun', 'openIssues'])
            ->orderBy('name')
            ->get();

        return ClientResource::collection($clients);
    }

    public function show(Client $client): ClientResource
    {
        $client->load(['latestCrawlRun', 'openIssues']);

        return new ClientResource($client);
    }

    public function issues(Request $request, Client $client): AnonymousResourceCollection
    {
        $query = $client->crawlIssues()->whereNull('resolved_at')->latest('detected_at');

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        return CrawlIssueResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'url', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:clients,slug'],
            'is_active' => ['nullable', 'boolean'],
            'slack_channel' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['domain'] = rtrim($validated['domain'], '/');
        $validated['is_active'] = $validated['is_active'] ?? true;

        $client = Client::create($validated);

        return response()->json([
            'data' => new ClientResource($client),
            'message' => "Client '{$client->name}' created.",
        ], 201);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clients' => ['required', 'array', 'min:1', 'max:100'],
            'clients.*.name' => ['required', 'string', 'max:255'],
            'clients.*.domain' => ['required', 'url', 'max:255'],
            'clients.*.slug' => ['nullable', 'string', 'max:255'],
            'clients.*.is_active' => ['nullable', 'boolean'],
            'clients.*.slack_channel' => ['nullable', 'string', 'max:255'],
            'clients.*.notes' => ['nullable', 'string'],
            'clients.*.settings' => ['nullable', 'array'],
        ]);

        $created = [];
        $skipped = [];

        foreach ($validated['clients'] as $clientData) {
            $clientData['slug'] = $clientData['slug'] ?? Str::slug($clientData['name']);
            $clientData['domain'] = rtrim($clientData['domain'], '/');
            $clientData['is_active'] = $clientData['is_active'] ?? true;

            if (Client::where('slug', $clientData['slug'])->exists()) {
                $skipped[] = $clientData['slug'];

                continue;
            }

            $created[] = Client::create($clientData);
        }

        return response()->json([
            'message' => count($created).' client(s) created, '.count($skipped).' skipped (duplicate slug).',
            'created' => ClientResource::collection(collect($created)),
            'skipped_slugs' => $skipped,
        ], 201);
    }

    public function crawl(Client $client): JsonResponse
    {
        CrawlClientJob::dispatch($client, triggeredManually: true);

        return response()->json([
            'message' => "Crawl dispatched for {$client->name}.",
        ]);
    }
}
