<?php

namespace App\Http\Resources;

use App\Models\CrawlIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CrawlIssue */
class CrawlIssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'crawl_run_id' => $this->crawl_run_id,
            'url' => $this->url,
            'issue_type' => $this->issue_type,
            'severity' => $this->severity,
            'context' => $this->context,
            'consecutive_detections' => $this->consecutive_detections,
            'confidence' => $this->confidence,
            'detected_at' => $this->detected_at instanceof \DateTimeInterface ? $this->detected_at->format('c') : $this->detected_at,
            'resolved_at' => $this->resolved_at instanceof \DateTimeInterface ? $this->resolved_at->format('c') : $this->resolved_at,
            'alerted_at' => $this->alerted_at instanceof \DateTimeInterface ? $this->alerted_at->format('c') : $this->alerted_at,
            'client' => new ClientResource($this->whenLoaded('client')),
        ];
    }
}
