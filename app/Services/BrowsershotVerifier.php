<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

class BrowsershotVerifier
{
    /**
     * Render a page with full JS execution and extract SEO signals from the DOM.
     *
     * @return array{h1: ?string, h1_count: int, meta_title: ?string, meta_description: ?string, word_count: int}|null
     */
    public function verify(string $url): ?array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
            Log::warning("BrowsershotVerifier rejected invalid URL: {$url}");

            return null;
        }

        try {
            $html = Browsershot::url($url)
                ->timeout(30)
                ->waitUntilNetworkIdle()
                ->bodyHtml();

            return $this->extractSignals($html);
        } catch (\Throwable $e) {
            Log::warning("BrowsershotVerifier failed for {$url}: {$e->getMessage()}");

            return null;
        }
    }

    private function extractSignals(string $html): array
    {
        $dom = new Crawler($html);

        $h1 = null;
        $h1Count = 0;
        try {
            $h1Count = $dom->filter('h1')->count();
            if ($h1Count > 0) {
                $h1 = trim($dom->filter('h1')->first()->text());
            }
        } catch (\Exception) {
        }

        $metaTitle = null;
        try {
            $titleNode = $dom->filter('title')->first();
            $metaTitle = $titleNode->count() ? trim($titleNode->text()) : null;
        } catch (\Exception) {
        }

        $metaDescription = null;
        try {
            $metaNode = $dom->filter('meta[name="description"]')->first();
            $metaDescription = $metaNode->count() ? trim($metaNode->attr('content') ?? '') : null;
        } catch (\Exception) {
        }

        $cleanHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $cleanHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $cleanHtml);
        $cleanHtml = preg_replace('/<nav\b[^>]*>.*?<\/nav>/si', '', $cleanHtml);
        $cleanHtml = preg_replace('/<footer\b[^>]*>.*?<\/footer>/si', '', $cleanHtml);
        $cleanHtml = preg_replace('/<header\b[^>]*>.*?<\/header>/si', '', $cleanHtml);
        $text = strip_tags($cleanHtml);
        $text = preg_replace('/\s+/', ' ', $text);
        $wordCount = str_word_count(trim($text));

        return [
            'h1' => $h1,
            'h1_count' => $h1Count,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'word_count' => $wordCount,
        ];
    }
}
