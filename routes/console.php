<?php

use App\Jobs\PruneOldCrawlRunsJob;
use App\Jobs\WeeklySummaryJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('watchdog:dispatch-crawls')->everyFifteenMinutes();
Schedule::command('watchdog:cleanup-stale-runs')->hourly();
Schedule::command('watchdog:dispatch-screenshots')->hourly();
Schedule::job(new PruneOldCrawlRunsJob)->dailyAt('02:00');
Schedule::job(new WeeklySummaryJob)->weeklyOn(1, '08:00');
