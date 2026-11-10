<?php

namespace App\Jobs;

use App\Models\ZipImport;
use App\Services\AuditService;
use App\Services\IngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ProcessZipImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 14400;

    public function __construct(public int $zipImportId)
    {
        $this->onQueue('imports'); // extraction has its own worker: it never waits behind slow OCR jobs
    }

    public function handle(IngestService $ingest): void
    {
        $zip = ZipImport::find($this->zipImportId);
        if (!$zip) {
            return;
        }
        $zip->update(['status' => 'processing']);

        $za = new ZipArchive();
        if ($za->open(storage_path('app/' . $zip->zip_path)) !== true) {
            $this->finish($zip, 'failed', 'The ZIP file is invalid or corrupted.');
            return;
        }

        // Collect real files from ANY folder depth (folders, sub-folders, ... are just part of the entry path).
        // Skip directory entries and OS junk, enforce count + total-size limits up front.
        $entries = [];
        $declared = 0;
        $folders = 0;
        $junk = 0;
        for ($i = 0; $i < $za->numFiles; $i++) {
            $st = $za->statIndex($i);
            if ($st === false) {
                continue;
            }
            $name = preg_replace('#^(\./)+#', '', str_replace('\\', '/', $st['name']));
            if ($name === '' || $this->isDirectory($za, $i, $name, $st)) {
                $folders++;
                continue;
            }
            $base = basename($name);
            if (str_starts_with($name, '__MACOSX/') || str_contains($name, '/__MACOSX/')
                || str_starts_with($base, '._') || in_array($base, ['.DS_Store', 'Thumbs.db', 'desktop.ini'], true)) {
                $junk++;
                continue;
            }
            $entries[] = ['index' => $i, 'name' => $name, 'size' => $st['size']];
            $declared += $st['size'];
        }
        if (!$entries) {
            $total = $za->numFiles;
            $za->close();
            $this->finish($zip, 'failed', "The ZIP contains no files ($total entries: $folders folder(s), $junk system file(s)).");
            return;
        }
        if (count($entries) > config('archive.max_files_per_zip')) {
            $za->close();
            $this->finish($zip, 'failed', 'The ZIP contains more files than the allowed limit (' . config('archive.max_files_per_zip') . ').');
            return;
        }
        if ($declared > config('archive.max_extracted_size')) {
            $za->close();
            $this->finish($zip, 'failed', 'The extracted size would exceed the allowed limit.');
            return;
        }
        $zip->update(['total_files' => count($entries)]);

        $tmpDir = storage_path('app/tmp/zip_' . $zip->uuid);
        File::ensureDirectoryExists($tmpDir);
        $report = [];
        $extractedTotal = 0;
        $needOcr = [];

        foreach ($entries as $e) {
            $status = 'failed';
            $message = '';
            try {
                // Zip Slip: the entry path is NEVER used on disk. We stream each entry into a random temp
                // file, and additionally reject traversal-style names outright.
                $segments = explode('/', $e['name']);
                if (str_starts_with($e['name'], '/') || preg_match('/^[A-Za-z]:/', $e['name']) || in_array('..', $segments, true)) {
                    throw new \RuntimeException('Unsafe path in ZIP.');
                }
                $ext = strtolower(pathinfo($e['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, config('archive.allowed_extensions'), true)) {
                    $status = 'unsupported';
                    $message = 'This file type is not supported.';
                } else {
                    $tmp = $tmpDir . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $in = $za->getStream($za->getNameIndex($e['index']));
                    if (!$in) {
                        throw new \RuntimeException('Cannot read entry.');
                    }
                    $out = fopen($tmp, 'wb');
                    $max = config('archive.max_file_size');
                    $bytes = stream_copy_to_stream($in, $out, $max + 1); // cap real bytes, not declared size
                    fclose($in);
                    fclose($out);
                    $extractedTotal += (int) $bytes;
                    if ($bytes > $max) {
                        throw new \RuntimeException('File exceeds the allowed size.');
                    }
                    if ($extractedTotal > config('archive.max_extracted_size')) {
                        throw new \RuntimeException('Extracted size limit reached.');
                    }
                    $r = $ingest->ingest($tmp, $e['name'], $zip->created_by, $zip->id, false); // save only; OCR is queued after ALL files are saved
                    $status = $r['status'];
                    if ($status === 'imported' && $r['document'] && in_array($r['document']->ocr_status, ['pending', 'processing', 'failed'], true)) {
                        $needOcr[] = $r['document']->id;
                    }
                    $message = $r['message'] . (($r['status'] === 'duplicate' && $r['document']) ? ' Existing: ' . $r['document']->original_filename : '');
                    @unlink($tmp);
                }
            } catch (\Throwable $ex) {
                $status = 'failed';
                $message = substr($ex->getMessage(), 0, 300);
                if (isset($tmp)) {
                    @unlink($tmp);
                }
            }

            if (count($report) < 5000) {
                $report[] = ['name' => $e['name'], 'status' => $status, 'message' => $message];
            }
            $col = ['imported' => 'imported_files', 'duplicate' => 'duplicate_files', 'unsupported' => 'unsupported_files', 'failed' => 'failed_files'][$status];
            $zip->increment($col);
            $zip->increment('processed_files');   // one bad file never stops the ZIP
        }
        $za->close();
        File::deleteDirectory($tmpDir);

        $zip->update(['report' => json_encode($report)]);
        $zip->refresh();
        $warn = $zip->duplicate_files + $zip->unsupported_files + $zip->failed_files;
        $status = $zip->imported_files === 0 ? ($warn > 0 && $zip->failed_files > 0 ? 'failed' : 'completed_warnings')
            : ($warn > 0 ? 'completed_warnings' : 'completed');
        $this->finish($zip, $status);

        // Every file is now saved as its own document and visible in the list. Only now start the OCR.
        foreach ($needOcr as $id) {
            ProcessDocument::dispatch($id);
        }
        \App\Services\QueueKicker::kick(); // make sure the OCR worker is running now (this job runs in the CLI)
    }

    /** A folder entry: trailing slash, or the directory attribute set by Windows / Unix zip tools. */
    private function isDirectory(ZipArchive $za, int $i, string $name, array $st): bool
    {
        if (str_ends_with($name, '/')) {
            return true;
        }
        if (($st['size'] ?? 1) === 0 && $za->getExternalAttributesIndex($i, $opsys, $attr)) {
            if ($opsys === ZipArchive::OPSYS_DOS && ($attr & 0x10)) {
                return true;
            }
            if ($opsys === ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0170000) === 0040000)) {
                return true;
            }
        }
        return false;
    }

    public function failed(\Throwable $e): void
    {
        if ($zip = ZipImport::find($this->zipImportId)) {
            $this->finish($zip, 'failed', substr($e->getMessage(), 0, 500));
        }
    }

    private function finish(ZipImport $zip, string $status, ?string $message = null): void
    {
        $zip->update(['status' => $status, 'message' => $message, 'completed_at' => now()]);
        AuditService::log('ZIP_IMPORT', null, null,
            "ZIP {$zip->original_filename}: $status ({$zip->imported_files} imported, {$zip->duplicate_files} duplicates, {$zip->failed_files} failed)", $zip->created_by);
    }
}
