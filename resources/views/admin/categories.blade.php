@extends('layouts.app')
@section('title', 'Categories')
@section('content')
<h4>Categories</h4>
<form method="post" action="{{ route('categories.store') }}" class="input-group mb-3" style="max-width:420px">@csrf
    <input name="name" class="form-control" placeholder="New category" required><button class="btn btn-primary">Add</button></form>
<div class="card shadow-sm"><table class="table mb-0">
    <thead><tr><th>Name</th><th>Documents</th><th></th></tr></thead>
    @foreach ($categories as $c)
        <tr>
            <td><form method="post" action="{{ route('categories.update', $c) }}" class="d-flex gap-2">@csrf @method('PUT')
                <input name="name" value="{{ $c->name }}" class="form-control form-control-sm" style="max-width:280px"><button class="btn btn-sm btn-outline-secondary">Rename</button></form></td>
            <td>{{ $c->documents_count }}</td>
            <td class="text-end"><form method="post" action="{{ route('categories.destroy', $c) }}" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
        </tr>
    @endforeach
</table></div>
@endsection
