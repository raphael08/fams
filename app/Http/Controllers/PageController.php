<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentPage;
use App\Models\Document;
use App\Services\AuditService;
use App\Services\PageFileService;
use App\Services\PageRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PageController extends Controller
{
    private function valid(Document $document, int $page): void
    {
        abort_unless($page >= 1 && $page <= $document->page_count, 404);
    }

    private function selection(Request $request, Document $document): array
    {
        $request->validate(['pages' => 'required|string|max:5000']);
        try {
            return PageRange::parse($request->input('pages'), $document->page_count);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }

    public function image(Document $document, int $page, PageFileService $files)
    {
        $this->valid($document, $page);
        return response()->file($files->image($document, $page), ['Cache-Control' => 'private, max-age=3600']);
    }

    public function ocr(Document $document, int $page)
    {
        $this->valid($document, $page);
        $p = $document->pages()->where('page_number', $page)->first();
        return response()->json([
            'page' => $page, 'text' => $p->ocr_text, 'confidence' => $p->ocr_confidence,
            'status' => $p->ocr_status, 'error' => $p->ocr_error,
        ]);
    }

    public function download(Document $document, int $page, PageFileService $files)
    {
        Gate::authorize('export');
        $this->valid($document, $page);
        AuditService::log('PAGE_DOWNLOAD', $document, [$page]);
        $tmp = $files->pdfForPages($document, [$page]);
        return response()->download($tmp, sprintf('%s_page_%03d.pdf', $document->downloadStem(), $page))->deleteFileAfterSend(true);
    }

    public function print(Document $document, int $page)
    {
        Gate::authorize('export');
        $this->valid($document, $page);
        AuditService::log('PAGE_PRINT', $document, [$page]);
        return view('documents.print', ['document' => $document, 'pages' => [$page]]);
    }

    public function downloadSelected(Request $request, Document $document, PageFileService $files)
    {
        Gate::authorize('export');
        $pages = $this->selection($request, $document);
        AuditService::log('SELECTED_PAGES_DOWNLOAD', $document, $pages);
        $tmp = $files->pdfForPages($document, $pages);
        return response()->download($tmp, $document->downloadStem() . '_selected_pages.pdf')->deleteFileAfterSend(true);
    }

    public function printSelected(Request $request, Document $document)
    {
        Gate::authorize('export');
        $pages = $this->selection($request, $document);
        AuditService::log('SELECTED_PAGES_PRINT', $document, $pages);
        return view('documents.print', ['document' => $document, 'pages' => $pages]);
    }

    public function retryOcr(Document $document, int $page)
    {
        Gate::authorize('manage');
        $this->valid($document, $page);
        $document->pages()->where('page_number', $page)->update(['ocr_status' => 'processing']);
        $document->update(['ocr_status' => 'processing']);
        AuditService::log('OCR_RETRY', $document, [$page]);
        ProcessDocumentPage::dispatch($document->id, $page);
        return response()->json(['queued' => true]);
    }
}
