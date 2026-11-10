@extends('layouts.app')
@section('title', 'Edit metadata')
@section('content')
<h4>Edit metadata</h4>
<p class="text-muted">{{ $document->original_filename }}. Manual corrections are authoritative and will not be overwritten by automatic classification.</p>
<form method="post" action="{{ route('documents.update', $document) }}" class="card shadow-sm"><div class="card-body row g-3">
    @csrf @method('PUT')
    <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $document->title) }}"></div>
    <div class="col-md-6"><label class="form-label">Category</label>
        <select name="document_category_id" class="form-select"><option value="">—</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('document_category_id', $document->document_category_id) == $c->id)>{{ $c->name }}</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label">Document type</label>
        <select name="document_type_id" class="form-select"><option value="">—</option>
            @foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('document_type_id', $document->document_type_id) == $t->id)>{{ $t->category?->name }} — {{ $t->name }}</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label">Person / party</label><input name="party" class="form-control" value="{{ old('party', $party) }}"></div>
    <div class="col-md-3"><label class="form-label">Year</label><input name="year" type="number" class="form-control" value="{{ old('year', $document->year) }}"></div>
    <div class="col-md-3"><label class="form-label">Document date</label><input name="document_date" type="date" class="form-control" value="{{ old('document_date', $document->document_date?->format('Y-m-d')) }}"></div>
    <div class="col-md-6"><label class="form-label">Document number</label><input name="document_number" class="form-control" value="{{ old('document_number', $document->document_number) }}"></div>
    <div class="col-md-6"><label class="form-label">Reference number</label><input name="reference_number" class="form-control" value="{{ old('reference_number', $document->reference_number) }}"></div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a href="{{ route('documents.show', $document) }}" class="btn btn-link">Cancel</a></div>
</div></form>
@endsection
