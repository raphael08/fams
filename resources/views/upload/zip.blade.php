@extends('layouts.app')
@section('title', 'ZIP import')
@section('content')
<h4>ZIP Import</h4>
<form method="post" action="{{ route('zip.store') }}" enctype="multipart/form-data" class="card shadow-sm mb-4" style="max-width:640px"><div class="card-body">
    @csrf
    <label class="form-label">Select ZIP</label>
    <input type="file" name="zip" class="form-control mb-2" accept=".zip" required>
    <div class="form-text mb-3">The ZIP is extracted automatically (nested folders included). Duplicates and unsupported files are reported, and one failed file never stops the import.</div>
    <button class="btn btn-primary"><i class="bi bi-file-earmark-zip"></i> Import ZIP</button>
</div></form>
<div class="card shadow-sm"><div class="card-header bg-body">Recent imports</div>
<div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>File</th><th>Total</th><th>Imported</th><th>Duplicates</th><th>Unsupported</th><th>Failed</th><th>Status</th><th></th></tr></thead>
    @forelse ($imports as $z)
        <tr><td>{{ $z->original_filename }}</td><td>{{ $z->total_files }}</td><td>{{ $z->imported_files }}</td><td>{{ $z->duplicate_files }}</td><td>{{ $z->unsupported_files }}</td><td>{{ $z->failed_files }}</td>
            <td>{{ $z->statusLabel() }}</td><td><a href="{{ route('zip.show', $z) }}">Details</a></td></tr>
    @empty <tr><td colspan="8" class="text-center text-muted py-3">No imports yet.</td></tr>
    @endforelse
</table></div></div>
@endsection
