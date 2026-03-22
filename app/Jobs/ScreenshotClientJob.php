<?php

namespace App\Jobs;

use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\PageScreenshot;
use App\Services\VisualDiffService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

class ScreenshotClientJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public Client $client,
    ) {
        $this->queue = 'crawl';
    }

    public function handle(): void
    {
        $settings = ClientSettings::for($this->client);
        $disk = config('filesystems.screenshots_disk', 'local');

        $monitoredUrls = $settings['monitored_urls'] ?? ['/'];

        foreach ($monitoredUrls as $path) {
            try {
                $fullUrl = $this->client->resolvedDomain().'/'.ltrim($path, '/');
                $slug = Str::slug(parse_url($fullUrl, PHP_URL_PATH) ?: 'homepage') ?: 'homepage';
                $date = now()->format('Y-m-d');
                $filePath = "screenshots/client-{$this->client->id}/{$slug}/{$date}.jpg";

                $absolutePath = Storage::disk($disk)->path($filePath);
                $directory = dirname($absolutePath);

                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                Browsershot::url($fullUrl)
                    ->windowSize(1440, 900)
                    ->fullPage()
                    ->timeout(30)
                    ->waitUntilNetworkIdle()
                    ->setScreenshotType('jpeg', 85)
                    ->save($absolutePath);

                $previousScreenshot = PageScreenshot::where('client_id', $this->client->id)
                    ->whereRaw('url = ?', [$fullUrl])
                    ->latest('captured_at')
                    ->first();

                $diffData = [];
                if ($previousScreenshot) {
                    $diffData = $this->computeDiff($previousScreenshot, $filePath, $disk, $settings);
                }

                PageScreenshot::create(array_merge([
                    'client_id' => $this->client->id,
                    'url' => $fullUrl,
                    'file_path' => $filePath,
                    'disk' => $disk,
                    'viewport_width' => 1440,
                    'full_page' => true,
                    'captured_at' => now(),
                    'previous_screenshot_id' => $previousScreenshot?->id,
                ], $diffData));
            } catch (\Throwable $e) {
                Log::warning("Screenshot error for {$this->client->name} ({$path}): {$e->getMessage()}");
            }
        }

        $this->client->update(['last_screenshot_at' => now()]);
    }

    private function computeDiff(PageScreenshot $previous, string $currentPath, string $disk, array $settings): array
    {
        try {
            $diffService = new VisualDiffService;

            return $diffService->compare(
                Storage::disk($previous->disk)->path($previous->file_path),
                Storage::disk($disk)->path($currentPath),
                $this->client->id,
                $settings['visual_diff_exclusion_zones'] ?? [],
            );
        } catch (\Throwable $e) {
            Log::warning("Visual diff failed: {$e->getMessage()}");

            return [];
        }
    }
}
