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

    public function crawl(Client $client): JsonResponse
    {
        CrawlClientJob::dispatch($client, triggeredManually: true);

        return response()->json([
            'message' => "Crawl dispatched for {$client->name}.",
        ]);
    }
}
