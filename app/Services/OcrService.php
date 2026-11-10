<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentPage;

class OcrService
{
    /** OCR the whole document (or only $pages) one page at a time; each result is saved as it arrives. */
    public function run(Document $doc, ?array $pages = null, bool $enhance = false): void
    {
        $args = ['--file', $doc->absolutePath(),
            '--dpi', config('archive.ocr_dpi'), '--low', config('archive.ocr_low_confidence')];
        if ($pages) {
            $args[] = '--pages';
            $args[] = implode(',', $pages);
        }
        if ($enhance) {
            $args[] = '--enhance';
        }

        $seen = 0;
        PythonRunner::stream('ocr.py', $args, function (string $line) use ($doc, &$seen) {
            $r = json_decode($line, true);
            if (!is_array($r) || !isset($r['page'])) {
                return;
            }
            $failed = !empty($r['error']);
            DocumentPage::where('document_id', $doc->id)->where('page_number', (int) $r['page'])->update([
                'ocr_text' => $failed ? null : ($r['text'] ?? ''),
                'ocr_confidence' => $failed ? null : ($r['confidence'] ?? null),
                'ocr_status' => $failed ? 'failed' : 'completed',
                'ocr_error' => $failed ? substr($r['error'], 0, 500) : null,
                'updated_at' => now(),
            ]);
            if (++$seen % 5 === 0) {
                $this->updateProgress($doc);
            }
        });
        $this->updateProgress($doc);
    }

    public function updateProgress(Document $doc): void
    {
        $done = DocumentPage::where('document_id', $doc->id)->whereIn('ocr_status', ['completed', 'failed'])->count();
        $doc->update(['ocr_progress' => $doc->page_count ? (int) floor($done * 100 / $doc->page_count) : 100]);
    }

    /** Derive the document status from its pages. */
    public function refreshStatus(Document $doc): void
    {
        $q = DocumentPage::where('document_id', $doc->id);
        $open = (clone $q)->whereIn('ocr_status', ['pending', 'processing'])->count();
        $failed = (clone $q)->where('ocr_status', 'failed')->count();
        $low = (clone $q)->where('ocr_status', 'completed')->where('ocr_confidence', '<', config('archive.ocr_low_confidence'))->count();

        $status = match (true) {
            $open > 0 => 'processing',
            $failed > 0 && $failed >= $doc->page_count => 'failed',
            $failed > 0 || $low > 0 => 'completed_warnings',
            default => 'completed',
        };
        $doc->update(['ocr_status' => $status, 'ocr_error' => $status === 'failed' ? ($doc->ocr_error ?: (DocumentPage::where('document_id', $doc->id)->where('ocr_status', 'failed')->value('ocr_error') ?: 'All pages failed OCR.')) : null]);
        $this->updateProgress($doc);
    }
}
