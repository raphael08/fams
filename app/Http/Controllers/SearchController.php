<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Services\AuditService;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search)
    {
        $q = trim((string) $request->query('q', ''));
        $filters = $request->only(['category', 'type', 'year']);
        $searched = $q !== '' || array_filter($filters);

        $results = null;
        if ($searched) {
            $results = $search->search($q, $filters);
            if ($request->query('page', 1) == 1) {
                AuditService::log('SEARCH', null, null, "q=\"$q\" " . json_encode(array_filter($filters)));
            }
        }
        return view('search.index', [
            'q' => $q, 'filters' => $filters, 'results' => $results, 'terms' => $search->terms($q),
            'categories' => DocumentCategory::orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get()->unique('name'),
            'years' => Document::whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function inDocument(Request $request, Document $document, SearchService $search)
    {
        $hits = $search->inDocument($document, (string) $request->query('q', ''));
        return response()->json(['count' => count($hits), 'hits' => $hits]);
    }
}
