<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\CrawlRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheck extends Command
{
    protected $signature = 'app:health-check';

    protected $description = 'Report application health status';

    public function handle(): int
    {
        $this->info('=== CGN Bot Health Check ===');
        $this->newLine();

        // DB connection
        try {
            DB::connection()->getPdo();
            $this->line('DB connection: <fg=green>OK</>');
        } catch (\Throwable $e) {
            $this->line('DB connection: <fg=red>FAIL</> - '.$e->getMessage());
        }

        // Redis connection
        try {
            Redis::ping();
            $this->line('Redis connection: <fg=green>OK</>');
        } catch (\Throwable $e) {
            $this->line('Redis connection: <fg=red>FAIL</> - '.$e->getMessage());
        }

        $this->newLine();

        // Counts
        $userCount = DB::table('users')->count();
        $this->line("Users: {$userCount}");

        $activeClients = Client::active()->count();
        $totalClients = Client::count();
        $this->line("Clients: {$activeClients} active / {$totalClients} total");

        // Latest crawl run
        $latestRun = CrawlRun::latest('started_at')->first();
        if ($latestRun) {
            $this->line("Latest crawl: {$latestRun->status} at {$latestRun->started_at}");
        } else {
            $this->line('Latest crawl: <fg=yellow>none</>');
        }

        // Pending queue jobs
        try {
            $pendingJobs = DB::table('jobs')->count();
            $this->line("Pending queue jobs: {$pendingJobs}");
        } catch (\Throwable) {
            $this->line('Pending queue jobs: <fg=yellow>N/A (jobs table missing)</>');
        }

        // Screenshot disk usage
        try {
            $disk = Storage::disk('screenshots');
            $files = $disk->allFiles();
            $totalSize = collect($files)->sum(fn (string $file) => $disk->size($file));
            $this->line('Screenshot disk: '.number_format($totalSize / 1024 / 1024, 2).' MB ('.count($files).' files)');
        } catch (\Throwable) {
            $this->line('Screenshot disk: <fg=yellow>N/A</>');
        }

        // Log file size
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $logSize = filesize($logPath);
            $this->line('Laravel log: '.number_format($logSize / 1024 / 1024, 2).' MB');
        } else {
            $this->line('Laravel log: <fg=yellow>no log file</>');
        }

        $this->newLine();
        $this->info('Health check complete.');

        return self::SUCCESS;
    }
}
