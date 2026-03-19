<?php

namespace App\Services;

use Imagick;

class VisualDiffService
{
    public function compare(string $previousPath, string $currentPath, int $clientId): array
    {
        if (! file_exists($previousPath) || ! file_exists($currentPath)) {
            return [];
        }

        $previous = new Imagick($previousPath);
        $current = new Imagick($currentPath);

        $previous->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);

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
        $diffMetric = $result[1];
        $percentage = round($diffMetric * 100, 2);

        $diffDate = now()->format('Y-m-d');
        $diffRelativePath = "screenshots/client-{$clientId}/diffs/diff-{$diffDate}-".uniqid().'.jpg';

        $diffFullPath = storage_path("app/private/{$diffRelativePath}");
        $diffDir = dirname($diffFullPath);

        if (! is_dir($diffDir)) {
            mkdir($diffDir, 0755, true);
        }

        $diffImage->setImageFormat('jpg');
        $diffImage->writeImage($diffFullPath);

        $previous->clear();
        $current->clear();
        $diffImage->clear();

        return [
            'diff_percentage' => $percentage,
            'diff_image_path' => $diffRelativePath,
        ];
    }
}
