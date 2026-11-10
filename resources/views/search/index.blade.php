@extends('layouts.app')
@section('title', 'Search')
@section('content')
<h4>Search Documents</h4>
<form class="card shadow-sm mb-4"><div class="card-body row g-2">
    <div class="col-12"><input name="q" class="form-control form-control-lg" placeholder='e.g. RAPHAEL APPEAL 2024   or   "NOTICE OF APPEAL"' value="{{ $q }}" autofocus></div>
    <div class="col-md-3"><label class="form-label small mb-0">Category</label><select name="category" class="form-select form-select-sm"><option value="">All</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Document Type</label><select name="type" class="form-select form-select-sm"><option value="">All</option>
        @foreach ($types as $t)<option value="{{ $t->id }}" @selected(($filters['type'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small mb-0">Year</label><select name="year" class="form-select form-select-sm"><option value="">All</option>
        @foreach ($years as $y)<option @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>@endforeach</select></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button></div>
</div></form>

@if ($results)
    <p class="text-muted">{{ $results->total() }} document(s) found</p>
    @forelse ($results as $d)
        @php $pgs = $d->matched_pages; $first = $pgs->first() ?? 1; @endphp
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <h6 class="mb-1"><a href="{{ route('documents.show', [$d, 'page' => $first, 'q' => $q]) }}">{{ $d->original_filename }}</a></h6>
                <a class="btn btn-sm btn-primary" href="{{ route('documents.show', [$d, 'page' => $first, 'q' => $q]) }}">Open</a>
            </div>
            <div class="small text-muted mb-2">
                Type: <strong>{{ $d->type?->name ?? '—' }}</strong> ·
                Person: <strong>{{ $d->party?->name ?? '—' }}</strong> ·
                Year: {{ $d->year ?? '—' }} · Category: {{ $d->category?->name ?? '—' }}
            </div>
            @if ($pgs->count())
                <div class="small mb-1">Matches: <strong>{{ $pgs->count() }}</strong> page(s)</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($pgs->take(20) as $n)
                        <a class="btn btn-sm btn-outline-primary py-0" title="Open Page {{ $n }}" href="{{ route('documents.show', [$d, 'page' => $n, 'q' => $q]) }}">Open Page {{ $n }}</a>
                    @endforeach
                    @if ($pgs->count() > 20)<span class="small text-muted align-self-center">+{{ $pgs->count() - 20 }} more (open the document to search within it)</span>@endif
                </div>
            @else
                <div class="small text-muted">Matched on document details.</div>
            @endif
        </div></div>
    @empty
        <div class="alert alert-light border">No documents matched your search.</div>
    @endforelse
    {{ $results->links() }}
@endif
@endsection
