<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentPage;
use App\Models\DocumentParty;
use App\Models\DocumentType;

class ClassifierService
{
    /** Filename + OCR text (first pages) -> category/type/party/year/numbers. Manual edits are never overwritten. */
    public function classify(Document $doc): void
    {
        if ($doc->metadata_source === 'manual') {
            return;
        }
        $text = DocumentPage::where('document_id', $doc->id)->where('ocr_status', 'completed')
            ->orderBy('page_number')->limit(3)->pluck('ocr_text')->implode("\n");

        $types = DocumentType::with('category')->get()
            ->map(fn ($t) => ['name' => $t->name, 'category' => $t->category?->name ?? 'Other'])->all();

        try {
            $out = PythonRunner::run('classify.py', [], 60, json_encode([
                'filename' => $doc->original_filename, 'text' => $text, 'types' => $types,
            ]));
            $r = json_decode($out, true) ?: [];
        } catch (\Throwable $e) {
            return; // classification is best-effort
        }

        $cat = !empty($r['category']) ? DocumentCategory::where('name', $r['category'])->first() : null;
        $type = null;
        if (!empty($r['type'])) {
            $type = DocumentType::where('name', $r['type'])->where('document_category_id', $cat?->id)->first()
                ?? DocumentType::where('name', $r['type'])->first();
        }
        $party = null;
        if (!empty($r['person'])) {
            $name = mb_substr(trim($r['person']), 0, 250);
            $party = DocumentParty::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? DocumentParty::create(['name' => $name]);
        }

        $doc->update(array_filter([
            'document_category_id' => $cat?->id,
            'document_type_id' => $type?->id,
            'document_party_id' => $party?->id,
            'year' => $r['year'] ?? null,
            'document_number' => $doc->document_number ?: ($r['document_number'] ?? null),
            'reference_number' => $doc->reference_number ?: ($r['reference_number'] ?? null),
        ], fn ($v) => $v !== null));
    }
}
