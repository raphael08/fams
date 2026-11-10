<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PageFileService
{
    /** Cached JPEG render of one page (created on first view). */
    public function image(Document $doc, int $page): string
    {
        $path = $doc->dir() . '/pages/p' . $page . '.jpg';
        if (!is_file($path)) {
            File::ensureDirectoryExists(dirname($path));
            PythonRunner::run('pdf.py', ['render', '--file', $doc->absolutePath(), '--page', $page,
                '--out', $path, '--dpi', config('archive.view_dpi')], 120);
        }
        return $path;
    }

    /** New PDF containing only $pages (original order). Caller deletes the temp file after sending. */
    public function pdfForPages(Document $doc, array $pages): string
    {
        $tmpDir = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDir);
        $out = $tmpDir . '/' . Str::uuid() . '.pdf';
        PythonRunner::run('generate_page.py', ['--file', $doc->absolutePath(), '--pages', implode(',', $pages), '--out', $out], 300);
        return $out;
    }

    public function searchablePath(Document $doc): string
    {
        return $doc->dir() . '/generated/searchable.pdf';
    }
}
