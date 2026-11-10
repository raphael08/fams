<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentPage;
use App\Services\AuditService;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Manual OCR retry of a single page, using the stronger enhancement pass. */
class ProcessDocumentPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $documentId, public int $page)
    {
    }

    public function handle(OcrService $ocr): void
    {
        $doc = Document::find($this->documentId);
        if (!$doc) {
            return;
        }
        try {
            $ocr->run($doc, [$this->page], true);
        } catch (\Throwable $e) {
            DocumentPage::where('document_id', $doc->id)->where('page_number', $this->page)
                ->update(['ocr_status' => 'failed', 'ocr_error' => substr($e->getMessage(), 0, 500)]);
            AuditService::log('OCR_FAILED', $doc, [$this->page], $e->getMessage(), $doc->created_by);
        }
        $ocr->refreshStatus($doc->refresh());
    }
}
