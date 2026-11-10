<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipImport extends Model
{
    protected $guarded = [];
    protected $casts = ['completed_at' => 'datetime'];

    public const STATUS_LABELS = [
        'queued' => 'Queued', 'processing' => 'Processing', 'completed' => 'Completed',
        'completed_warnings' => 'Completed With Warnings', 'failed' => 'Failed',
    ];

    public function getRouteKeyName(): string { return 'uuid'; }
    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function isFinished(): bool { return in_array($this->status, ['completed', 'completed_warnings', 'failed']); }
}
