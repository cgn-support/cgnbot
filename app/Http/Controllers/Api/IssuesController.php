<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrawlIssueResource;
use App\Models\CrawlIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IssuesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CrawlIssue::open()
            ->with('client')
            ->latest('detected_at');

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        return CrawlIssueResource::collection($query->get());
    }
}
