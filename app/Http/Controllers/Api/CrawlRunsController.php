<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrawlRunResource;
use App\Models\CrawlRun;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CrawlRunsController extends Controller
{
    public function latest(): AnonymousResourceCollection
    {
        $runs = CrawlRun::with('client')
            ->latest()
            ->limit(10)
            ->get();

        return CrawlRunResource::collection($runs);
    }
}
