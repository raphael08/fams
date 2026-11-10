@extends('layouts.app')
@section('title', 'Deleted documents')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Deleted documents</h4>
    <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Documents</a>
</div>
<p class="text-muted">Documents removed by users are hidden from everyone but kept here. Restore them, or delete them permanently (files and database records).
    Uploading the same file again restores it automatically.</p>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead><tr><th>Filename</th><th>Category</th><th>Pages</th><th>Deleted by</th><th>Deleted at</th><th class="text-end">Actions</th></tr></thead>
    @foreach ($documents as $d)
    <tr>
        <td>{{ $d->original_filename }}</td>
        <td>{{ $d->category?->name ?? '—' }}</td>
        <td>{{ $d->page_count }}</td>
        <td>{{ $d->deleter?->name ?? '—' }}</td>
        <td class="text-nowrap">{{ $d->deleted_at->format('Y-m-d H:i') }}</td>
        <td class="text-end text-nowrap">
            <form method="post" action="{{ route('documents.restore', $d->uuid) }}" class="d-inline">@csrf
                <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button></form>
            <form method="post" action="{{ route('documents.purge', $d->uuid) }}" class="d-inline" onsubmit="return confirm('PERMANENTLY delete this document and all its files? This cannot be undone.')">@csrf @method('DELETE')
                <button class="btn btn-sm btn-danger"><i class="bi bi-x-octagon"></i> Delete permanently</button></form>
        </td>
    </tr>
    @endforeach
</table></div></div>
@endsection
