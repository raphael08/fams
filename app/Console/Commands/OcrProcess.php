<?php

namespace App\Console\Commands;

use App\Jobs\GenerateSearchablePdf;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Console\Command;

/**
 * Run OCR right now in this terminal (no queue worker needed). Good for diagnosing "Pending".
 *   php artisan ocr:process            -> every document that is pending/failed/processing
 *   php artisan ocr:process {uuid}     -> one document
 *   php artisan ocr:process --reset    -> redo all pages
 */
class OcrProcess extends Command
{
    protected $signature = 'ocr:process {uuid? : Document UUID} {--reset : Re-OCR every page}';
    protected $description = 'Run OCR synchronously for pending/failed documents and build the searchable PDF';

    public function handle(): int
    {
        $docs = $this->argument('uuid')
            ? Document::where('uuid', $this->argument('uuid'))->get()
            : Document::whereIn('ocr_status', ['pending', 'processing', 'failed'])->get();

        if ($docs->isEmpty()) {
            $this->info('Nothing to process.');
            return self::SUCCESS;
        }

        foreach ($docs as $doc) {
            $this->info("OCR: {$doc->original_filename} ({$doc->page_count} pages)");
            $q = DocumentPage::where('document_id', $doc->id);
            if ($this->option('reset')) {
                $q->update(['ocr_status' => 'pending']);
            }
            ProcessDocument::dispatchSync($doc->id);
            $doc->refresh();
            $this->line("  status: {$doc->ocr_status}" . ($doc->ocr_error ? " - {$doc->ocr_error}" : ''));
            $this->info('  building searchable PDF...');
            GenerateSearchablePdf::dispatchSync($doc->id);
        }
        return self::SUCCESS;
    }
}
