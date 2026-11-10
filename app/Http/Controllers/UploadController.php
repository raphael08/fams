<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessZipImport;
use App\Models\ZipImport;
use App\Services\AuditService;
use App\Services\IngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function form()
    {
        return view('upload.document');
    }

    public function store(Request $request, IngestService $ingest)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $r = $ingest->ingest($file->getRealPath(), $file->getClientOriginalName(), $request->user()->id);

        return match ($r['status']) {
            'imported' => redirect()->route('documents.show', $r['document'])->with('success', $r['message']),
            'duplicate' => back()->with('warning', 'This document already exists in the archive (not deleted). Existing document: ')
                ->with('duplicate_url', route('documents.show', $r['document']))
                ->with('duplicate_name', $r['document']->original_filename),
            default => back()->with('error', $r['message']),
        };
    }

    public function zipForm()
    {
        return view('upload.zip', ['imports' => ZipImport::latest()->limit(15)->get()]);
    }

    public function zipStore(Request $request)
    {
        $request->validate(['zip' => 'required|file|mimes:zip|max:' . intdiv(config('archive.max_zip_size'), 1024)]);
        $file = $request->file('zip');

        $uuid = (string) Str::uuid();
        $rel = "zip_imports/$uuid.zip";
        File::ensureDirectoryExists(storage_path('app/zip_imports'));
        $file->move(storage_path('app/zip_imports'), "$uuid.zip");

        $zip = ZipImport::create([
            'uuid' => $uuid, 'original_filename' => $file->getClientOriginalName(), 'zip_path' => $rel,
            'status' => 'queued', 'created_by' => $request->user()->id,
        ]);
        AuditService::log('ZIP_IMPORT', null, null, 'ZIP queued: ' . $zip->original_filename);
        ProcessZipImport::dispatch($zip->id);

        return redirect()->route('zip.show', $zip);
    }

    public function zipShow(ZipImport $zip)
    {
        return view('upload.zip-show', ['zip' => $zip, 'report' => json_decode($zip->report ?? '[]', true)]);
    }

    public function zipStatus(ZipImport $zip)
    {
        \App\Services\QueueKicker::kick(); // self-heal: start any worker that is missing while jobs are waiting
        return response()->json([
            'total' => $zip->total_files, 'processed' => $zip->processed_files, 'imported' => $zip->imported_files,
            'duplicates' => $zip->duplicate_files, 'unsupported' => $zip->unsupported_files, 'failed' => $zip->failed_files,
            'status' => $zip->status, 'label' => $zip->statusLabel(), 'finished' => $zip->isFinished(), 'message' => $zip->message,
        ]);
    }
}
