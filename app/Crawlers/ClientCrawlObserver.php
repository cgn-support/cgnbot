<?php

namespace App\Crawlers;

use App\Models\CrawledPage;
use App\Models\CrawlRun;
use GuzzleHttp\Exception\RequestException;
use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;
use Spatie\Crawler\Enums\ResourceType;
use Spatie\Crawler\TransferStatistics;
use Symfony\Component\DomCrawler\Crawler;

class ClientCrawlObserver extends CrawlObserver
{
    protected int $pagesCrawled = 0;

    public function __construct(
        protected CrawlRun $crawlRun,
        protected int $clientId,
        protected string $baseDomain,
    ) {}

    public function crawled(string $url, CrawlResponse $response, CrawlProgress $progress): void
    {
        $html = $response->body();
        $statusCode = $response->status();
        $dom = $response->dom();

        $transferStats = $response->transferStats();
        $responseTimeMs = $transferStats ? (int) round($transferStats->transferTimeInMs() ?? 0) : 0;

        CrawledPage::updateOrCreate(
            [
                'crawl_run_id' => $this->crawlRun->id,
                'url' => $url,
            ],
            [
                'client_id' => $this->clientId,
                'status_code' => $statusCode,
                'redirect_url' => $response->wasRedirected() ? collect($response->redirectHistory())->last() : null,
                'redirect_count' => count($response->redirectHistory()),
                'canonical_url' => $this->extractCanonical($dom),
                'canonical_is_self' => $this->extractCanonicalIsSelf($dom, $url),
                'meta_title' => $this->extractTitle($dom),
                'meta_title_length' => mb_strlen($this->extractTitle($dom) ?? ''),
                'meta_description' => $this->extractMetaDescription($dom),
                'meta_description_length' => mb_strlen($this->extractMetaDescription($dom) ?? ''),
                'h1' => $this->extractH1($dom),
                'h1_count' => $this->countH1($dom),
                'word_count' => $this->countWords($html),
                'is_indexable' => $this->extractIndexability($dom),
                'has_schema_markup' => $this->hasSchemaMarkup($dom, $html),
                'schema_types' => $this->extractSchemaTypes($dom, $html),
                'internal_links_count' => $this->countLinks($dom, true),
                'external_links_count' => $this->countLinks($dom, false),
                'response_time_ms' => $responseTimeMs,
                'page_hash' => hash('sha256', $html),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $this->pagesCrawled++;
    }

    public function crawlFailed(
        string $url,
        RequestException $requestException,
        CrawlProgress $progress,
        ?string $foundOnUrl = null,
        ?string $linkText = null,
        ?ResourceType $resourceType = null,
        ?TransferStatistics $transferStats = null,
    ): void {
        $statusCode = 0;
        if ($requestException->hasResponse()) {
            $statusCode = $requestException->getResponse()->getStatusCode();
        }

        CrawledPage::updateOrCreate(
            [
                'crawl_run_id' => $this->crawlRun->id,
                'url' => $url,
            ],
            [
                'client_id' => $this->clientId,
                'status_code' => $statusCode,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $this->pagesCrawled++;
    }

    public function getPagesCrawled(): int
    {
        return $this->pagesCrawled;
    }

    private function extractTitle(Crawler $dom): ?string
    {
        try {
            $title = $dom->filter('title')->first();

            return $title->count() ? trim($title->text()) : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractMetaDescription(Crawler $dom): ?string
    {
        try {
            $meta = $dom->filter('meta[name="description"]')->first();

            return $meta->count() ? trim($meta->attr('content') ?? '') : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractH1(Crawler $dom): ?string
    {
        try {
            $h1 = $dom->filter('h1')->first();

            return $h1->count() ? trim($h1->text()) : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function countH1(Crawler $dom): int
    {
        try {
            return $dom->filter('h1')->count();
        } catch (\Exception) {
            return 0;
        }
    }

    private function extractCanonical(Crawler $dom): ?string
    {
        try {
            $link = $dom->filter('link[rel="canonical"]')->first();

            return $link->count() ? trim($link->attr('href') ?? '') : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractCanonicalIsSelf(Crawler $dom, string $url): ?bool
    {
        $canonical = $this->extractCanonical($dom);

        if ($canonical === null) {
            return null;
        }

        return rtrim($canonical, '/') === rtrim($url, '/');
    }

    private function extractIndexability(Crawler $dom): bool
    {
        try {
            $robots = $dom->filter('meta[name="robots"]');
            if ($robots->count() === 0) {
                return true;
            }

            $content = strtolower($robots->first()->attr('content') ?? '');

            return ! str_contains($content, 'noindex');
        } catch (\Exception) {
            return true;
        }
    }

    private function hasSchemaMarkup(Crawler $dom, string $html): bool
    {
        if ($dom->filter('[itemtype]')->count() > 0) {
            return true;
        }

        return str_contains($html, 'application/ld+json');
    }

    private function extractSchemaTypes(Crawler $dom, string $html): ?array
    {
        $types = [];

        $dom->filter('[itemtype]')->each(function (Crawler $node) use (&$types) {
            $itemtype = $node->attr('itemtype') ?? '';
            if (preg_match('#schema\.org/([A-Za-z]+)#', $itemtype, $matches)) {
                $types[] = $matches[1];
            }
        });

        $dom->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$types) {
            try {
                $json = json_decode($node->text(), true);
                if (isset($json['@type'])) {
                    $types[] = $json['@type'];
                }
                if (isset($json['@graph'])) {
                    foreach ($json['@graph'] as $item) {
                        if (isset($item['@type'])) {
                            $types[] = $item['@type'];
                        }
                    }
                }
            } catch (\Exception) {
            }
        });

        $types = array_unique(array_filter($types));

        return empty($types) ? null : array_values($types);
    }

    private function countLinks(Crawler $dom, bool $internal): int
    {
        $baseHost = parse_url($this->baseDomain, PHP_URL_HOST);
        $count = 0;

        try {
            $dom->filter('a[href]')->each(function (Crawler $node) use ($baseHost, $internal, &$count) {
                $href = $node->attr('href') ?? '';
                if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    return;
                }

                $linkHost = parse_url($href, PHP_URL_HOST);

                if ($linkHost === null || $linkHost === false) {
                    if ($internal) {
                        $count++;
                    }

                    return;
                }

                if ($internal && $linkHost === $baseHost) {
                    $count++;
                } elseif (! $internal && $linkHost !== $baseHost) {
                    $count++;
                }
            });
        } catch (\Exception) {
        }

        return $count;
    }

    private function countWords(string $html): int
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/si', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/si', '', $html);

        $dom = new Crawler($html);

        $mainContent = null;
        foreach (['main', 'article', '[role="main"]'] as $selector) {
            try {
                $node = $dom->filter($selector)->first();
                if ($node->count() > 0) {
                    $mainContent = $node->text();
                    break;
                }
            } catch (\Exception) {
            }
        }

        $text = $mainContent ?? strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        return str_word_count(trim($text));
    }
}
