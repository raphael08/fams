@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h4 class="mb-3">Dashboard</h4>
<div class="row g-3 mb-4">
    @foreach ($stats as $label => $n)
        <div class="col-6 col-lg-3">
            <div class="card h-100 shadow-sm"><div class="card-body">
                <div class="text-muted small">{{ $label }}</div>
                <div class="stat">{{ number_format($n) }}</div>
            </div></div>
        </div>
    @endforeach
</div>
<div class="card shadow-sm">
    <div class="card-header bg-body">Recently added</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Filename</th><th>Pages</th><th>OCR</th><th>Added</th></tr></thead>
            @forelse ($recent as $d)
                <tr>
                    <td><a href="{{ route('documents.show', $d) }}">{{ $d->original_filename }}</a></td>
                    <td>{{ $d->page_count }}</td>
                    <td><span class="badge text-bg-{{ $d->statusColor() }}">{{ $d->statusLabel() }}</span></td>
                    <td>{{ $d->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No documents yet.</td></tr>
            @endforelse
        </table>
    </div>
</div>
@endsection
