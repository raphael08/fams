<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['document_date' => 'date'];

    public const STATUS_LABELS = [
        'pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed',
        'completed_warnings' => 'Completed with warnings', 'failed' => 'Failed',
    ];
    public const STATUS_COLORS = [
        'pending' => 'secondary', 'processing' => 'primary', 'completed' => 'success',
        'completed_warnings' => 'warning', 'failed' => 'danger',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function category(): BelongsTo { return $this->belongsTo(DocumentCategory::class, 'document_category_id'); }
    public function type(): BelongsTo { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function party(): BelongsTo { return $this->belongsTo(DocumentParty::class, 'document_party_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function deleter(): BelongsTo { return $this->belongsTo(User::class, 'deleted_by'); }
    public function pages(): HasMany { return $this->hasMany(DocumentPage::class)->orderBy('page_number'); }

    /** Absolute path of the untouched original. Never exposed to users. */
    public function absolutePath(): string { return storage_path('app/' . $this->storage_path); }
    public function dir(): string { return storage_path('app/documents/' . $this->uuid); }
    public function extension(): string { return strtolower(pathinfo($this->storage_path, PATHINFO_EXTENSION)); }
    public function isPdf(): bool { return $this->extension() === 'pdf'; }

    /** Filename-safe stem for generated downloads, e.g. NOTICE_OF_APPEAL_2024 */
    public function downloadStem(): string
    {
        $stem = pathinfo($this->original_filename, PATHINFO_FILENAME);
        return trim(preg_replace('/[^A-Za-z0-9]+/', '_', $stem), '_') ?: 'document';
    }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->ocr_status] ?? $this->ocr_status; }
    public function statusColor(): string { return self::STATUS_COLORS[$this->ocr_status] ?? 'secondary'; }
    public function isBusy(): bool { return in_array($this->ocr_status, ['pending', 'processing']); }
}
