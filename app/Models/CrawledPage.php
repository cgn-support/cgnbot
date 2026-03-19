<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawledPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'crawl_run_id',
        'client_id',
        'url',
        'status_code',
        'redirect_url',
        'redirect_count',
        'canonical_url',
        'canonical_is_self',
        'meta_title',
        'meta_title_length',
        'meta_description',
        'meta_description_length',
        'h1',
        'h1_count',
        'word_count',
        'is_indexable',
        'in_sitemap',
        'has_schema_markup',
        'schema_types',
        'internal_links_count',
        'external_links_count',
        'broken_links_count',
        'response_time_ms',
        'page_hash',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'canonical_is_self' => 'boolean',
            'is_indexable' => 'boolean',
            'in_sitemap' => 'boolean',
            'has_schema_markup' => 'boolean',
            'schema_types' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isOk(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isBroken(): bool
    {
        return $this->status_code >= 400;
    }

    public function isRedirect(): bool
    {
        return $this->status_code >= 300 && $this->status_code < 400;
    }
}
