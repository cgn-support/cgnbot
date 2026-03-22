<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array<string, mixed>|null $settings
 *
 * @method static Builder<static> active()
 * @method static Builder<static> dueForCrawl()
 * @method static Builder<static> dueForScreenshot()
 */
class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'is_active',
        'last_crawled_at',
        'last_screenshot_at',
        'settings',
        'slack_channel',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_crawled_at' => 'datetime',
            'last_screenshot_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function crawlRuns(): HasMany
    {
        return $this->hasMany(CrawlRun::class);
    }

    public function latestCrawlRun(): HasOne
    {
        return $this->hasOne(CrawlRun::class)->latestOfMany();
    }

    public function crawledPages(): HasMany
    {
        return $this->hasMany(CrawledPage::class);
    }

    public function crawlIssues(): HasMany
    {
        return $this->hasMany(CrawlIssue::class);
    }

    public function openIssues(): HasMany
    {
        return $this->hasMany(CrawlIssue::class)->whereNull('resolved_at');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(PageScreenshot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForCrawl(Builder $query): Builder
    {
        $defaultHours = CrawlerSetting::current()->default_crawl_frequency_hours;

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($defaultHours) {
                $q->whereNull('last_crawled_at')
                    ->orWhereRaw(
                        'last_crawled_at <= NOW() - INTERVAL COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings, \'$.crawl_frequency_hours\')), ?) HOUR',
                        [$defaultHours]
                    );
            });
    }

    public function scopeDueForScreenshot(Builder $query): Builder
    {
        $defaultHours = CrawlerSetting::current()->default_screenshot_frequency_hours;

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($defaultHours) {
                $q->whereNull('last_screenshot_at')
                    ->orWhereRaw(
                        'last_screenshot_at <= NOW() - INTERVAL COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings, \'$.screenshot_frequency_hours\')), ?) HOUR',
                        [$defaultHours]
                    );
            });
    }

    public function resolvedDomain(): string
    {
        return rtrim($this->domain, '/');
    }

    public function openCriticalIssuesCount(): int
    {
        return $this->crawlIssues()
            ->whereNull('resolved_at')
            ->where('severity', 'critical')
            ->count();
    }
}
