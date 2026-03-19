<?php

namespace App\Jobs;

use App\Crawlers\ClientSettings;
use App\Models\Client;
use App\Models\PageScreenshot;
use App\Services\VisualDiffService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScreenshotClientJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public Client $client,
    ) {
        $this->queue = 'crawl';
    }

    public function handle(): void
    {
        $settings = ClientSettings::for($this->client);
        $apiKey = config('services.siteshot.key');
        $disk = config('filesystems.screenshots_disk', 'local');

        if (empty($apiKey)) {
            Log::warning("SiteShot API key not configured, skipping screenshots for {$this->client->name}");

            return;
        }

        $monitoredUrls = $settings['monitored_urls'] ?? ['/'];

        foreach ($monitoredUrls as $path) {
            try {
                $fullUrl = $this->client->resolvedDomain().'/'.ltrim($path, '/');
                $slug = Str::slug(parse_url($fullUrl, PHP_URL_PATH) ?: 'homepage') ?: 'homepage';
                $date = now()->format('Y-m-d');
                $filePath = "screenshots/client-{$this->client->id}/{$slug}/{$date}.jpg";

                $response = Http::get('https://api.siteshot.app/v1/screenshot', [
                    'token' => $apiKey,
                    'url' => $fullUrl,
                    'width' => 1440,
                    'full_page' => 1,
                    'format' => 'jpg',
                    'quality' => 85,
                ]);

                if ($response->failed()) {
                    Log::warning("Screenshot failed for {$fullUrl}: HTTP {$response->status()}");

                    continue;
                }

                Storage::disk($disk)->put($filePath, $response->body());

                $previousScreenshot = PageScreenshot::where('client_id', $this->client->id)
                    ->whereRaw('url = ?', [$fullUrl])
                    ->latest('captured_at')
                    ->first();

                $diffData = [];
                if ($previousScreenshot) {
                    $diffData = $this->computeDiff($previousScreenshot, $filePath, $disk);
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

    private function computeDiff(PageScreenshot $previous, string $currentPath, string $disk): array
    {
        try {
            $diffService = new VisualDiffService;

            return $diffService->compare(
                Storage::disk($previous->disk)->path($previous->file_path),
                Storage::disk($disk)->path($currentPath),
                $this->client->id,
            );
        } catch (\Throwable $e) {
            Log::warning("Visual diff failed: {$e->getMessage()}");

            return [];
        }
    }
}
