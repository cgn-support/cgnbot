<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PageScreenshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'url',
        'file_path',
        'disk',
        'siteshot_job_id',
        'viewport_width',
        'full_page',
        'previous_screenshot_id',
        'diff_percentage',
        'diff_image_path',
        'captured_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'full_page' => 'boolean',
            'captured_at' => 'datetime',
            'diff_percentage' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function previousScreenshot(): BelongsTo
    {
        return $this->belongsTo(static::class, 'previous_screenshot_id');
    }

    public function publicUrl(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }
}
