<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentType extends Model
{
    protected $table = 'document_types';
    protected $guarded = [];

    public function category(): BelongsTo { return $this->belongsTo(DocumentCategory::class, 'document_category_id'); }
}
