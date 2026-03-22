<?php

namespace App\Analyzers;

use App\Analyzers\Checks\BrokenLinksCheck;
use App\Analyzers\Checks\CanonicalMismatchCheck;
use App\Analyzers\Checks\CrawlCheck;
use App\Analyzers\Checks\DuplicateTitleCheck;
use App\Analyzers\Checks\HomepageDownCheck;
use App\Analyzers\Checks\MissingH1Check;
use App\Analyzers\Checks\MissingMetaDescriptionCheck;
use App\Analyzers\Checks\MissingTitleCheck;
use App\Analyzers\Checks\MultipleH1Check;
use App\Analyzers\Checks\NewPagesDetectedCheck;
use App\Analyzers\Checks\NoindexOnMonitoredUrlCheck;
use App\Analyzers\Checks\PageDisappearedCheck;
use App\Analyzers\Checks\RedirectChainCheck;
use App\Analyzers\Checks\SiteWideNoindexCheck;
use App\Analyzers\Checks\SlowPageCheck;
use App\Analyzers\Checks\ThinContentCheck;
use App\Analyzers\Checks\TitleTooLongCheck;
use App\Analyzers\Checks\TitleTooShortCheck;
use App\Analyzers\Checks\VisualRegressionCheck;
use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IssueAnalyzer
{
    /** @return array<class-string<CrawlCheck>> */
    protected function checks(): array
    {
        return [
            HomepageDownCheck::class,
            BrokenLinksCheck::class,
            NoindexOnMonitoredUrlCheck::class,
            SiteWideNoindexCheck::class,
            MissingTitleCheck::class,
            DuplicateTitleCheck::class,
            TitleTooLongCheck::class,
            TitleTooShortCheck::class,
            MissingMetaDescriptionCheck::class,
            MissingH1Check::class,
            MultipleH1Check::class,
            ThinContentCheck::class,
            SlowPageCheck::class,
            RedirectChainCheck::class,
            CanonicalMismatchCheck::class,
            PageDisappearedCheck::class,
            NewPagesDetectedCheck::class,
            VisualRegressionCheck::class,
        ];
    }

    public function run(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings): Collection
    {
        return $this->runChecks($crawlRun, $client, $currentPages, $previousPages, $settings, $this->checks());
    }

    /**
     * @param  array<class-string<CrawlCheck>>  $checkClasses
     */
    public function runChecks(CrawlRun $crawlRun, Client $client, Collection $currentPages, Collection $previousPages, array $settings, array $checkClasses): Collection
    {
        $allIssues = collect();

        foreach ($checkClasses as $checkClass) {
            try {
                $check = new $checkClass;
                $issues = $check->run($crawlRun, $client, $currentPages, $previousPages, $settings);
                $allIssues = $allIssues->merge($issues);
            } catch (\Throwable $e) {
                Log::error("Check {$checkClass} failed for client {$client->id}: {$e->getMessage()}");
            }
        }

        return $allIssues;
    }
}
