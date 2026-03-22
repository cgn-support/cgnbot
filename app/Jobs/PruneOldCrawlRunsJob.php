<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneOldCrawlRunsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        $settings = CrawlerSetting::current();
        $keepCount = $settings->crawl_runs_to_keep;
        $retentionDays = $settings->resolved_issues_retention_days;

        Client::active()->each(function (Client $client) use ($keepCount) {
            $completedRunIds = CrawlRun::where('client_id', $client->id)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->pluck('id');

            if ($completedRunIds->count() <= $keepCount) {
                return;
            }

            $keepIds = $completedRunIds->take($keepCount);
            $pruneIds = $completedRunIds->diff($keepIds);

            if ($pruneIds->isEmpty()) {
                return;
            }

            $columns = [
                'id', 'crawl_run_id', 'client_id', 'url', 'status_code', 'redirect_url', 'redirect_count',
                'canonical_url', 'canonical_is_self', 'meta_title', 'meta_title_length',
                'meta_description', 'meta_description_length', 'h1', 'h1_count', 'word_count',
                'is_indexable', 'in_sitemap', 'has_schema_markup', 'schema_types',
                'internal_links_count', 'external_links_count', 'broken_links_count',
                'response_time_ms', 'page_hash', 'first_seen_at', 'last_seen_at',
                'created_at', 'updated_at',
            ];

            $columnList = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, $pruneIds->count(), '?'));

            DB::statement(
                "INSERT INTO crawled_pages_archive ({$columnList}, archived_at)
                SELECT {$columnList}, NOW()
                FROM crawled_pages
                WHERE crawl_run_id IN ({$placeholders})",
                $pruneIds->values()->all()
            );

            DB::table('crawled_pages')->whereIn('crawl_run_id', $pruneIds)->delete();
            CrawlRun::whereIn('id', $pruneIds)->delete();

            Log::info("Pruned {$pruneIds->count()} old crawl runs for client {$client->name}");
        });

        CrawlIssue::whereNotNull('resolved_at')
            ->where('resolved_at', '<', now()->subDays($retentionDays))
            ->delete();

        Log::info('Pruning complete');
    }
}
