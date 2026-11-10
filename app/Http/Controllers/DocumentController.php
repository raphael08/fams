<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSearchablePdf;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentParty;
use App\Models\DocumentType;
use App\Services\AuditService;
use App\Services\PageFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = Document::with(['category', 'type', 'party'])->latest();
        foreach (['category' => 'document_category_id', 'type' => 'document_type_id', 'year' => 'year', 'status' => 'ocr_status'] as $in => $col) {
            if ($request->filled($in)) {
                $q->where($col, $request->input($in));
            }
        }
        return view('documents.index', [
            'documents' => $q->get(),
            'trashedCount' => Gate::allows('admin') ? Document::onlyTrashed()->count() : 0,
            'categories' => DocumentCategory::orderBy('name')->get(),
        ]);
    }

    /** Partial delete: hidden from everyone, files kept. An administrator can restore or purge it. */
    public function destroy(Document $document)
    {
        $document->update(['deleted_by' => auth()->id()]);
        $document->delete();
        AuditService::log('DOCUMENT_DELETE', $document, null, "Deleted {$document->original_filename} (can be restored by an administrator)");
        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }

    public function trash()
    {
        return view('documents.trash', ['documents' => Document::onlyTrashed()->with(['deleter', 'category'])->latest('deleted_at')->get()]);
    }

    public function restore(string $uuid)
    {
        $document = Document::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $document->restore();
        $document->update(['deleted_by' => null]);
        if ($document->isBusy() || $document->ocr_status === 'failed') {
            ProcessDocument::dispatch($document->id);
        }
        AuditService::log('RESTORE', $document, null, "Restored {$document->original_filename}");
        return back()->with('success', 'Document restored.');
    }

    /** Complete delete (administrator): database rows and every stored file. */
    public function purge(string $uuid)
    {
        $document = Document::withTrashed()->where('uuid', $uuid)->firstOrFail();
        AuditService::log('DOCUMENT_PURGE', $document, null, "Permanently deleted {$document->original_filename}");
        \Illuminate\Support\Facades\File::deleteDirectory($document->dir());
        \App\Models\DocumentPage::where('document_id', $document->id)->delete();
        $document->forceDelete();
        return back()->with('success', 'Document permanently deleted.');
    }

    /** The viewer. ?page=27&q=raphael jumps straight to a page / pre-runs the in-document search. */
    public function show(Request $request, Document $document)
    {
        AuditService::log('DOCUMENT_VIEW', $document);
        $document->load(['category', 'type', 'party']);
        return view('documents.show', [
            'document' => $document,
            'pages' => $document->pages()->get(['page_number', 'ocr_confidence', 'ocr_status']),
            'startPage' => max(1, min($document->page_count, (int) $request->query('page', 1))),
            'startQuery' => (string) $request->query('q', ''),
        ]);
    }

    public function edit(Document $document)
    {
        return view('documents.edit', [
            'document' => $document,
            'categories' => DocumentCategory::orderBy('name')->get(),
            'types' => DocumentType::with('category')->orderBy('name')->get(),
            'party' => $document->party?->name,
        ]);
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'document_type_id' => 'nullable|exists:document_types,id',
            'party' => 'nullable|string|max:250',
            'year' => 'nullable|integer|min:1800|max:2200',
            'document_date' => 'nullable|date',
            'document_number' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
        ]);
        $party = filled($data['party'] ?? null)
            ? (DocumentParty::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['party']))])->first()
                ?? DocumentParty::create(['name' => trim($data['party'])]))
            : null;
        unset($data['party']);

        $document->update($data + ['document_party_id' => $party?->id, 'metadata_source' => 'manual']);
        AuditService::log('METADATA_UPDATE', $document, null, 'Metadata edited manually');
        return redirect()->route('documents.show', $document)->with('success', 'Metadata saved.');
    }

    public function download(Request $request, Document $document)
    {
        Gate::authorize('export');
        if (!$request->boolean('inline')) {
            AuditService::log('DOCUMENT_DOWNLOAD', $document);
        }
        $headers = ['Content-Type' => $document->mime_type];
        return $request->boolean('inline')
            ? response()->file($document->absolutePath(), $headers)
            : response()->download($document->absolutePath(), $document->original_filename, $headers);
    }

    public function print(Document $document)
    {
        Gate::authorize('export');
        AuditService::log('DOCUMENT_PRINT', $document);
        return view('documents.print-all', ['document' => $document]);
    }

    public function status(Document $document)
    {
        if ($document->isBusy()) {
            \App\Services\QueueKicker::kick(); // self-heal if the worker is not running
        }
        return response()->json(['status' => $document->ocr_status, 'label' => $document->statusLabel(),
            'progress' => $document->ocr_progress, 'busy' => $document->isBusy(),
            'searchable' => is_file(app(PageFileService::class)->searchablePath($document))]);
    }

    /** Re-OCR the pages that are pending or failed. */
    public function retryOcr(Document $document)
    {
        Gate::authorize('manage');
        $this->queueOcrRetry($document);
        return back()->with('success', 'OCR retry queued.');
    }

    /** Retry several documents at once (checkboxes on the OCR page). */
    public function retrySelected(Request $request)
    {
        Gate::authorize('manage');
        $data = $request->validate(['ids' => 'required|array|min:1|max:1000', 'ids.*' => 'integer']);

        $workerBusy = \App\Services\QueueKicker::workerRunning('default');
        $queued = 0;
        $skipped = 0;
        foreach (Document::whereIn('id', $data['ids'])->get() as $document) {
            if ($document->ocr_status === 'processing' && $workerBusy) {
                $skipped++; // being processed right now: queuing it again would run it twice
                continue;
            }
            $this->queueOcrRetry($document);
            $queued++;
        }

        $msg = "$queued document(s) queued for OCR retry.";
        if ($skipped) {
            $msg .= " $skipped skipped because they are being processed right now.";
        }
        return back()->with('success', $msg);
    }

    private function queueOcrRetry(Document $document): void
    {
        $pages = $document->pages()->whereIn('ocr_status', ['pending', 'failed'])->pluck('page_number')->all();
        if (!$pages) {
            $pages = null; // nothing outstanding: redo everything
        }
        $document->pages()->when($pages, fn ($q) => $q->whereIn('page_number', $pages))->update(['ocr_status' => 'pending']);
        $document->update(['ocr_status' => 'pending', 'ocr_progress' => 0, 'ocr_error' => null]);
        AuditService::log('OCR_RETRY', $document, $pages);
        ProcessDocument::dispatch($document->id, $pages);
    }

    public function generateSearchable(Document $document)
    {
        Gate::authorize('manage');
        GenerateSearchablePdf::dispatch($document->id);
        return back()->with('success', 'Searchable PDF is being generated. Refresh in a few minutes.');
    }

    public function downloadSearchable(Document $document)
    {
        Gate::authorize('export');
        $path = app(PageFileService::class)->searchablePath($document);
        abort_unless(is_file($path), 404, 'The searchable PDF has not been generated yet.');
        AuditService::log('DOCUMENT_DOWNLOAD', $document, null, 'Searchable PDF');
        return response()->download($path, $document->downloadStem() . '_searchable.pdf');
    }
}
