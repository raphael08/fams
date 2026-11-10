<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AuditService;
use App\Services\ClassifierService;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** OCR a whole document (or a subset of pages), then classify it. */
class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 14400;

    public function __construct(public int $documentId, public ?array $pages = null)
    {
    }

    public function handle(OcrService $ocr, ClassifierService $classifier): void
    {
        $doc = Document::find($this->documentId);
        if (!$doc) {
            return;
        }
        $doc->update(['ocr_status' => 'processing', 'ocr_error' => null]);
        AuditService::log('OCR_STARTED', $doc, $this->pages, null, $doc->created_by);

        try {
            $ocr->run($doc, $this->pages);
            $doc->refresh();
            $ocr->refreshStatus($doc);
            $classifier->classify($doc->refresh());
            if ($this->pages === null && $doc->ocr_status !== 'failed') {
                GenerateSearchablePdf::dispatch($doc->id);
            }
            AuditService::log('OCR_COMPLETED', $doc, null, 'Status: ' . $doc->ocr_status, $doc->created_by);
        } catch (\Throwable $e) {
            $this->markFailed($doc, $e);
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($doc = Document::find($this->documentId)) {
            $this->markFailed($doc, $e);
        }
    }

    private function markFailed(Document $doc, \Throwable $e): void
    {
        $doc->update(['ocr_status' => 'failed', 'ocr_error' => substr($e->getMessage(), 0, 1000)]);
        AuditService::log('OCR_FAILED', $doc, null, $e->getMessage(), $doc->created_by);
    }
}
