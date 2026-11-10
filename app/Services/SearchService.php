<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    /** 'RAPHAEL "NOTICE OF APPEAL" 2024' -> ['RAPHAEL', 'NOTICE OF APPEAL', '2024'] */
    public function terms(string $q): array
    {
        preg_match_all('/"([^"]+)"|(\S+)/u', $q, $m, PREG_SET_ORDER);
        $terms = [];
        foreach ($m as $x) {
            $t = trim(($x[1] ?? '') !== '' ? $x[1] : ($x[2] ?? ''));
            if ($t !== '') {
                $terms[] = $t;
            }
        }
        return array_slice($terms, 0, 10);
    }

    private function like(string $t): string
    {
        return '%' . addcslashes($t, '\\%_') . '%';
    }

    /** A document matches when EVERY term is found in its metadata or in the OCR text of some page. */
    public function search(string $q, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $terms = $this->terms($q);
        $query = Document::with(['category', 'type', 'party'])->latest();

        foreach ($terms as $t) {
            $like = $this->like($t);
            $query->where(function ($w) use ($like) {
                $w->where('original_filename', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('document_number', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhereHas('party', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('type', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('pages', fn ($p) => $p->where('ocr_text', 'like', $like));
            });
        }
        if (!empty($filters['category'])) {
            $query->where('document_category_id', $filters['category']);
        }
        if (!empty($filters['type'])) {
            $query->where('document_type_id', $filters['type']);
        }
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        $results = $query->paginate($perPage)->withQueryString();

        // Which pages matched (any term) for the documents on this results page.
        $byDoc = collect();
        if ($terms && $results->count()) {
            $byDoc = DocumentPage::whereIn('document_id', $results->pluck('id'))
                ->where(function ($w) use ($terms) {
                    foreach ($terms as $t) {
                        $w->orWhere('ocr_text', 'like', $this->like($t));
                    }
                })
                ->orderBy('page_number')->get(['document_id', 'page_number'])
                ->groupBy('document_id')->map(fn ($g) => $g->pluck('page_number'));
        }
        $results->getCollection()->each(fn ($d) => $d->matched_pages = $byDoc->get($d->id, collect()));

        return $results;
    }

    /** Pages of one document containing any term, with a short snippet. */
    public function inDocument(Document $doc, string $q, int $limit = 500): array
    {
        $terms = $this->terms($q);
        if (!$terms) {
            return [];
        }
        $rows = DocumentPage::where('document_id', $doc->id)
            ->where(function ($w) use ($terms) {
                foreach ($terms as $t) {
                    $w->orWhere('ocr_text', 'like', $this->like($t));
                }
            })
            ->orderBy('page_number')->limit($limit)->get(['page_number', 'ocr_text']);

        return $rows->map(function ($r) use ($terms) {
            $text = preg_replace('/\s+/u', ' ', (string) $r->ocr_text);
            $snippet = '';
            foreach ($terms as $t) {
                if (($pos = mb_stripos($text, $t)) !== false) {
                    $snippet = mb_substr($text, max(0, $pos - 40), 120);
                    break;
                }
            }
            return ['page' => $r->page_number, 'snippet' => $snippet];
        })->all();
    }
}
