@extends('layouts.app')
@section('title', 'Document types')
@section('content')
<h4>Document Types</h4>
<form method="post" action="{{ route('types.store') }}" class="row g-2 mb-3" style="max-width:640px">@csrf
    <div class="col-5"><select name="document_category_id" class="form-select">@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-5"><input name="name" class="form-control" placeholder="New document type" required></div>
    <div class="col-2"><button class="btn btn-primary w-100">Add</button></div>
</form>
<div class="card shadow-sm"><table class="table table-sm mb-0">
    <thead><tr><th>Category</th><th>Type</th><th></th></tr></thead>
    @foreach ($types as $t)
        <tr><td>{{ $t->category?->name ?? '—' }}</td><td>{{ $t->name }}</td>
        <td class="text-end"><form method="post" action="{{ route('types.destroy', $t) }}" onsubmit="return confirm('Delete this type?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0">Delete</button></form></td></tr>
    @endforeach
</table></div>
@endsection
