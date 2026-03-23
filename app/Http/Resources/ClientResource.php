<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'is_active' => $this->is_active,
            'last_crawled_at' => $this->last_crawled_at instanceof \DateTimeInterface ? $this->last_crawled_at->format('c') : $this->last_crawled_at,
            'slack_channel' => $this->slack_channel,
            'latest_crawl_run' => new CrawlRunResource($this->whenLoaded('latestCrawlRun')),
            'open_critical_count' => $this->when(
                $this->relationLoaded('openIssues'),
                fn () => $this->openIssues->where('severity', 'critical')->count(),
            ),
            'open_issues_count' => $this->when(
                $this->relationLoaded('openIssues'),
                fn () => $this->openIssues->count(),
            ),
        ];
    }
}
