<?php

namespace App\ValueObjects;

class CrawlHealthResult
{
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $reason = null,
    ) {}

    public static function pass(): self
    {
        return new self(healthy: true);
    }

    public static function fail(string $reason): self
    {
        return new self(healthy: false, reason: $reason);
    }
}
