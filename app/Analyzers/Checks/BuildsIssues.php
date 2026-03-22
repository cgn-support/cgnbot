<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;

trait BuildsIssues
{
    protected function issue(
        CrawlRun $crawlRun,
        Client $client,
        string $url,
        string $issueType,
        string $severity,
        array $context = [],
        int $confidence = 100,
    ): array {
        return [
            'client_id' => $client->id,
            'crawl_run_id' => $crawlRun->id,
            'url' => $url,
            'issue_type' => $issueType,
            'severity' => $severity,
            'context' => $context,
            'confidence' => $confidence,
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
