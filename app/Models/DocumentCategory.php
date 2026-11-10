<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    protected $table = 'document_categories';
    protected $guarded = [];

    public function types(): HasMany { return $this->hasMany(DocumentType::class, 'document_category_id'); }
    public function documents(): HasMany { return $this->hasMany(Document::class, 'document_category_id'); }
}
