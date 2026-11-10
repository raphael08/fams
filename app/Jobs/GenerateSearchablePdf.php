<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AuditService;
use App\Services\PageFileService;
use App\Services\PythonRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

/** Builds generated/searchable.pdf (scan image + invisible text). The original is never modified. */
class GenerateSearchablePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 14400;

    public function __construct(public int $documentId)
    {
    }

    public function handle(PageFileService $files): void
    {
        $doc = Document::find($this->documentId);
        if (!$doc) {
            return;
        }
        $final = $files->searchablePath($doc);
        $tmp = $final . '.part';
        File::ensureDirectoryExists(dirname($final));
        @unlink($tmp);
        try {
            PythonRunner::stream('generate_page.py', ['--file', $doc->absolutePath(), '--searchable',
                '--out', $tmp, '--dpi', config('archive.ocr_dpi')], fn () => null);
            rename($tmp, $final);
        } catch (\Throwable $e) {
            @unlink($tmp);
            AuditService::log('OCR_FAILED', $doc, null, 'Searchable PDF failed: ' . $e->getMessage(), $doc->created_by);
        }
    }
}
