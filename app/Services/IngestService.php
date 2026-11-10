<?php

namespace App\Services;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Shared by single upload and ZIP import. */
class IngestService
{
    /**
     * @return array{status:string,message:string,document:?Document}
     *   status: imported | duplicate | unsupported | failed
     */
    public function ingest(string $tmpPath, string $originalName, ?int $userId, ?int $zipImportId = null, bool $dispatchOcr = true): array
    {
        $originalName = basename(str_replace('\\', '/', $originalName));
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, config('archive.allowed_extensions', []), true)) {
            return $this->result('unsupported', 'This file type is not supported.');
        }

        $size = filesize($tmpPath);
        if ($size === false || $size === 0) {
            return $this->result('failed', 'The file is empty.');
        }

        if ($size > config('archive.max_file_size')) {
            return $this->result('failed', 'The file is larger than the allowed size.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $allowedMimes = config('archive.mime_map')[$ext] ?? [];
        if (!in_array($mime, $allowedMimes, true)) {
            return $this->result('failed', "The file content ($mime) does not match its extension.");
        }

        $hash = hash_file('sha256', $tmpPath);
        $existing = Document::withTrashed()->where('sha256_hash', $hash)->first();
        if ($existing && $existing->trashed()) {
            // Same file was deleted earlier (partial delete): let the upload proceed by bringing it back.
            return $this->restoreDeleted($existing, $tmpPath, $originalName, $userId, $zipImportId, $dispatchOcr);
        }
        if ($existing) {
            AuditService::log('DUPLICATE_DETECTED', $existing, null, "Duplicate of upload: $originalName", $userId);
            return $this->result('duplicate', 'This document already exists in the archive.', $existing);
        }

        $uuid = (string) Str::uuid();
        $rel = "documents/$uuid/original/original.$ext";
        $abs = storage_path("app/$rel");

        File::ensureDirectoryExists(dirname($abs));
        if (!copy($tmpPath, $abs)) {
            return $this->result('failed', 'Could not store the file.');
        }

        try {
            $rawOutput = PythonRunner::run('pdf.py', ['info', '--file', $abs], 120);
            $info = json_decode($rawOutput, true);
            $pages = (int) ($info['page_count'] ?? 0);

            if ($pages < 1) {
                throw new RuntimeException('no pages found in PDF');
            }
        } catch (Throwable $e) {
            // Clean up created folder on exception
            File::deleteDirectory(storage_path("app/documents/$uuid"));

            return $this->result('failed', 'The file is unreadable, corrupted or password protected.');
        }

        $doc = DB::transaction(function () use ($uuid, $originalName, $rel, $mime, $size, $hash, $pages, $userId, $zipImportId) {
            $doc = Document::create([
                'uuid' => $uuid,
                'original_filename' => $originalName,
                'storage_path' => $rel,
                'mime_type' => $mime,
                'file_size' => $size,
                'sha256_hash' => $hash,
                'page_count' => $pages,
                'title' => pathinfo($originalName, PATHINFO_FILENAME),
                'zip_import_id' => $zipImportId,
                'created_by' => $userId,
            ]);

            $now = now();
            foreach (array_chunk(range(1, $pages), 500) as $chunk) {
                DocumentPage::insert(array_map(fn ($n) => [
                    'document_id' => $doc->id,
                    'page_number' => $n,
                    'ocr_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return $doc;
        });

        AuditService::log($zipImportId ? 'ZIP_IMPORT' : 'UPLOAD', $doc, null, "Imported $originalName ($pages pages)", $userId);
        if ($dispatchOcr) {
            ProcessDocument::dispatch($doc->id);
        }

        return $this->result('imported', 'Uploaded. OCR has been queued.', $doc);
    }

    private function restoreDeleted(Document $doc, string $tmpPath, string $originalName, ?int $userId, ?int $zipImportId, bool $dispatchOcr): array
    {
        if (!is_file($doc->absolutePath())) {                 // files are normally kept on a partial delete
            File::ensureDirectoryExists(dirname($doc->absolutePath()));
            if (!copy($tmpPath, $doc->absolutePath())) {
                return $this->result('failed', 'Could not store the file.');
            }
        }

        $doc->restore();
        $doc->update(['original_filename' => $originalName, 'created_by' => $userId, 'zip_import_id' => $zipImportId, 'deleted_by' => null]);
        AuditService::log('RESTORE', $doc, null, "Re-uploaded $originalName: the deleted document was restored", $userId);

        if ($dispatchOcr && in_array($doc->ocr_status, ['pending', 'processing', 'failed'], true)) {
            ProcessDocument::dispatch($doc->id);
        }

        return $this->result('imported', 'This document had been deleted earlier. It was restored with its existing OCR text.', $doc);
    }

    private function result(string $status, string $message, ?Document $document = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'document' => $document,
        ];
    }
}
