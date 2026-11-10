@extends('layouts.app')
@section('title', 'Audit logs')
@section('content')
<h4>Audit Logs</h4>
<form class="row g-2 mb-3">
    <div class="col-md-3"><select name="action" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All actions</option>@foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="user" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All users</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
</form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Document</th><th>Pages</th><th>Description</th><th>IP</th></tr></thead>
    @foreach ($logs as $l)
        <tr><td class="text-nowrap">{{ $l->created_at }}</td><td>{{ $l->user?->name ?? 'system' }}</td><td><code>{{ $l->action }}</code></td>
            <td>@if ($l->document_id)<a href="{{ route('documents.index') }}">#{{ $l->document_id }}</a>@endif</td>
            <td class="text-break" style="max-width:140px">{{ $l->page_numbers }}</td><td class="small">{{ $l->description }}</td><td class="small">{{ $l->ip_address }}</td></tr>
    @endforeach
</table></div></div>
<div class="mt-3"></div>
@endsection
