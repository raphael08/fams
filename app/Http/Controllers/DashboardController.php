<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\User;
use App\Models\ZipImport;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'Total Documents' => Document::count(),
            'Total Pages' => DocumentPage::whereHas('document')->count(),
            'OCR Completed' => Document::whereIn('ocr_status', ['completed', 'completed_warnings'])->count(),
            'OCR Processing' => Document::whereIn('ocr_status', ['pending', 'processing'])->count(),
            'OCR Failed' => Document::where('ocr_status', 'failed')->count(),
            'ZIP Imports' => ZipImport::count(),
            'Duplicates' => DB::table('audit_logs')->where('action', 'DUPLICATE_DETECTED')->count(),
            'Documents Added Today' => Document::whereDate('created_at', today())->count(),
        ];

        return view('dashboard', ['stats' => $stats, 'recent' => Document::latest()->limit(8)->get()]);
    }

    public function reports()
    {
        return view('reports', [
            'byCategory' => Document::leftJoin('document_categories as c', 'c.id', '=', 'documents.document_category_id')
                ->selectRaw("COALESCE(c.name, 'Unclassified') as label, COUNT(*) as n, SUM(page_count) as pages")->groupBy('label')->orderByDesc('n')->get(),
            'byType' => Document::leftJoin('document_types as t', 't.id', '=', 'documents.document_type_id')
                ->selectRaw("COALESCE(t.name, 'Unclassified') as label, COUNT(*) as n")->groupBy('label')->orderByDesc('n')->get(),
            'byYear' => Document::selectRaw("COALESCE(CAST(year AS CHAR), 'Unknown') as label, COUNT(*) as n")->groupBy('label')->orderBy('label')->get(),
            'byStatus' => Document::selectRaw('ocr_status as label, COUNT(*) as n')->groupBy('label')->get(),
            'lowPages' => DocumentPage::whereHas('document')->where('ocr_status', 'completed')->where('ocr_confidence', '<', config('archive.ocr_low_confidence'))->count(),
            'failedPages' => DocumentPage::whereHas('document')->where('ocr_status', 'failed')->count(),
            'byUser' => User::leftJoin('documents as d', 'd.created_by', '=', 'users.id')
                ->selectRaw('users.name as label, COUNT(d.id) as n')->groupBy('users.id', 'users.name')->orderByDesc('n')->get(),
        ]);
    }
}
