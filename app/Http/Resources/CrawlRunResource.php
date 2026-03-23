<?php

namespace App\Http\Resources;

use App\Models\CrawlRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CrawlRun */
class CrawlRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'status' => $this->status,
            'triggered_manually' => $this->triggered_manually,
            'pages_crawled' => $this->pages_crawled,
            'pages_with_issues' => $this->pages_with_issues,
            'critical_issues_found' => $this->critical_issues_found,
            'warning_issues_found' => $this->warning_issues_found,
            'info_issues_found' => $this->info_issues_found,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->durationSeconds(),
            'error_message' => $this->error_message,
            'client' => new ClientResource($this->whenLoaded('client')),
        ];
    }
}
