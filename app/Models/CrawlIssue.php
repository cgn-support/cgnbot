<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'crawl_run_id',
        'url',
        'issue_type',
        'severity',
        'context',
        'detected_at',
        'resolved_at',
        'alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'alerted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeUnalerted(Builder $query): Builder
    {
        return $query->whereNull('alerted_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }
}
