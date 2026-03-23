<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\HealthReportRenderer;
use App\Services\HealthReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateHealthReport extends Command
{
    protected $signature = 'watchdog:health-report {client?} {--period=30}';

    protected $description = 'Generate SEO health reports for clients';

    public function handle(HealthReportService $reportService, HealthReportRenderer $renderer): void
    {
        $periodDays = (int) $this->option('period');
        $clientSlug = $this->argument('client');

        $clients = $clientSlug
            ? Client::where('slug', $clientSlug)->get()
            : Client::active()->get();

        if ($clients->isEmpty()) {
            $this->error('No clients found.');

            return;
        }

        foreach ($clients as $client) {
            $this->info("Generating report for: {$client->name}...");

            $reportData = $reportService->generate($client, $periodDays);
            $markdown = $renderer->toMarkdown($reportData);

            $filename = Str::slug($client->name).'-'.now()->format('Y-m-d').'.md';
            $path = storage_path("app/reports/{$filename}");

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $markdown);

            $this->info("Report saved: {$path}");
        }

        $this->comment("Generated {$clients->count()} report(s).");
    }
}
