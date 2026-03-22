<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use ImagickPixelException;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

class VisualDiffService
{
    private ImageHash $hasher;

    public function __construct(?ImageHash $hasher = null)
    {
        if (! extension_loaded('imagick')) {
            throw new \RuntimeException('Imagick extension is required for visual diff');
        }

        $this->hasher = $hasher ?? new ImageHash(new PerceptualHash);
    }

    /**
     * @param  array<int, array{label?: string, x: int, y: int, width: int|string, height: int}>  $exclusionZones
     * @return array{diff_percentage?: float, diff_image_path?: string}
     */
    public function compare(
        string $previousPath,
        string $currentPath,
        int $clientId,
        array $exclusionZones = [],
    ): array {
        foreach ([$previousPath, $currentPath] as $path) {
            if (! file_exists($path) || ! is_readable($path) || filesize($path) === 0) {
                return [];
            }
        }

        $tempFiles = [];

        try {
            $prevProcessed = $previousPath;
            $currProcessed = $currentPath;

            if ($exclusionZones !== []) {
                [$prevProcessed, $currProcessed, $tempFiles] = $this->applyExclusionMasks(
                    $previousPath, $currentPath, $exclusionZones,
                );
            }

            $prevHash = $this->hasher->hash($prevProcessed);
            $currHash = $this->hasher->hash($currProcessed);
            $distance = $prevHash->distance($currHash);
            $percentage = round(($distance / 64) * 100, 2);

            $diffImageData = $this->generateDiffImage($previousPath, $currentPath, $clientId);

            return array_merge(
                ['diff_percentage' => $percentage],
                $diffImageData,
            );
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{diff_image_path?: string}
     */
    private function generateDiffImage(string $previousPath, string $currentPath, int $clientId): array
    {
        $previous = null;
        $current = null;
        $diffImage = null;

        try {
            $memoryLimit = (int) (config('watchdog.imagick_memory_mb', 256) * 1024 * 1024);

            $previous = new Imagick($previousPath);
            $current = new Imagick($currentPath);

            $previous->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $memoryLimit);

            $prevWidth = $previous->getImageWidth();
            $prevHeight = $previous->getImageHeight();
            $currWidth = $current->getImageWidth();
            $currHeight = $current->getImageHeight();

            if ($prevWidth !== $currWidth || $prevHeight !== $currHeight) {
                $targetWidth = (int) round(min($prevWidth, $currWidth) * 0.5);
                $targetHeight = (int) round(min($prevHeight, $currHeight) * 0.5);

                $previous->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
                $current->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
            }

            $result = $current->compareImages($previous, Imagick::METRIC_ROOTMEANSQUAREDERROR);

            $diffImage = $result[0];

            $diffDate = now()->format('Y-m-d');
            $diffRelativePath = "screenshots/client-{$clientId}/diffs/diff-{$diffDate}-".bin2hex(random_bytes(8)).'.jpg';

            $diffFullPath = storage_path("app/private/{$diffRelativePath}");
            $diffDir = dirname($diffFullPath);

            if (! is_dir($diffDir)) {
                @mkdir($diffDir, 0755, true);
            }

            $diffImage->setImageFormat('jpg');
            $diffImage->writeImage($diffFullPath);

            return ['diff_image_path' => $diffRelativePath];
        } catch (ImagickException $e) {
            Log::warning('Imagick error during visual diff', [
                'error' => $e->getMessage(),
                'previous_size' => filesize($previousPath),
                'current_size' => filesize($currentPath),
                'client_id' => $clientId,
                'memory_usage' => memory_get_usage(true),
            ]);

            return [];
        } catch (ImagickPixelException $e) {
            Log::warning('Imagick pixel error during visual diff', [
                'error' => $e->getMessage(),
                'client_id' => $clientId,
            ]);

            return [];
        } finally {
            $previous?->clear();
            $current?->clear();
            $diffImage?->clear();
        }
    }

    /**
     * @param  array<int, array{label?: string, x: int, y: int, width: int|string, height: int}>  $zones
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function applyExclusionMasks(string $prevPath, string $currPath, array $zones): array
    {
        $tempFiles = [];

        $prevImage = new Imagick($prevPath);
        $currImage = new Imagick($currPath);

        try {
            $this->maskZones($prevImage, $zones);
            $this->maskZones($currImage, $zones);

            $prevTemp = tempnam(sys_get_temp_dir(), 'watchdog-prev-');
            $currTemp = tempnam(sys_get_temp_dir(), 'watchdog-curr-');

            $prevImage->writeImage($prevTemp);
            $currImage->writeImage($currTemp);

            $tempFiles[] = $prevTemp;
            $tempFiles[] = $currTemp;

            return [$prevTemp, $currTemp, $tempFiles];
        } finally {
            $prevImage->clear();
            $currImage->clear();
        }
    }

    /**
     * @param  array<int, array{label?: string, x: int, y: int, width: int|string, height: int}>  $zones
     */
    private function maskZones(Imagick $image, array $zones): void
    {
        $imageWidth = $image->getImageWidth();
        $imageHeight = $image->getImageHeight();

        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('#808080'));

        foreach ($zones as $zone) {
            $x = (int) $zone['x'];
            $y = (int) $zone['y'];
            $width = $this->resolveSize($zone['width'], $imageWidth);
            $height = $this->resolveSize($zone['height'], $imageHeight);

            $draw->rectangle($x, $y, $x + $width - 1, $y + $height - 1);
        }

        $image->drawImage($draw);
    }

    private function resolveSize(int|string $value, int $reference): int
    {
        if (is_string($value) && str_ends_with($value, '%')) {
            $percent = (float) rtrim($value, '%');

            return (int) round($reference * $percent / 100);
        }

        return (int) $value;
    }
}
