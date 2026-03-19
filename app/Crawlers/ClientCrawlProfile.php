<?php

namespace App\Crawlers;

use Spatie\Crawler\CrawlProfiles\CrawlProfile;

class ClientCrawlProfile implements CrawlProfile
{
    public function __construct(
        protected string $baseHost,
        protected array $excludedPatterns = [],
    ) {}

    public function shouldCrawl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== $this->baseHost) {
            return false;
        }

        foreach ($this->excludedPatterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
