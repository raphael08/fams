<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPage extends Model
{
    protected $guarded = [];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }

    public function isLowConfidence(): bool
    {
        return $this->ocr_status === 'completed'
            && $this->ocr_confidence !== null
            && (float) $this->ocr_confidence < config('archive.ocr_low_confidence');
    }
}
