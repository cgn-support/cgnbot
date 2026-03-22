<?php

namespace App\Analyzers\Checks;

use App\Models\Client;
use App\Models\CrawlRun;
use App\Models\PageScreenshot;
use Illuminate\Support\Collection;

class VisualRegressionCheck implements CrawlCheck
{
    use BuildsIssues;

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        $issues = collect();

        $threshold = $settings['visual_diff_threshold'] ?? 15;

        $previousRun = CrawlRun::where('client_id', $client->id)
            ->where('status', 'completed')
            ->where('id', '!=', $crawlRun->id)
            ->latest()
            ->first();

        $sinceDate = $previousRun->started_at ?? now()->subDay();

        $screenshots = PageScreenshot::where('client_id', $client->id)
            ->where('captured_at', '>=', $sinceDate)
            ->whereNotNull('diff_percentage')
            ->where('diff_percentage', '>', $threshold)
            ->get();

        foreach ($screenshots as $screenshot) {
            $issues->push($this->issue(
                $crawlRun,
                $client,
                $screenshot->url,
                'VisualRegressionCheck',
                'warning',
                [
                    'diff_percentage' => $screenshot->diff_percentage,
                    'threshold' => $threshold,
                    'screenshot_url' => $screenshot->file_path,
                ],
                confidence: 70,
            ));
        }

        return $issues;
    }
}
