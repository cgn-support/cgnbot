<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $context
 *
 * @method static Builder<static> open()
 * @method static Builder<static> critical()
 * @method static Builder<static> unalerted()
 * @method static Builder<static> unverified()
 */
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
        'consecutive_detections',
        'first_detected_run_id',
        'confidence',
        'verified_at',
        'verified_by',
        'detected_at',
        'resolved_at',
        'alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'consecutive_detections' => 'integer',
            'confidence' => 'integer',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'alerted_at' => 'datetime',
            'verified_at' => 'datetime',
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

    public function firstDetectedRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class, 'first_detected_run_id');
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

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('verified_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function issueKey(): string
    {
        return $this->url.'|'.$this->issue_type;
    }
}
