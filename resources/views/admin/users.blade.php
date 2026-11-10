@extends('layouts.app')
@section('title', 'Users')
@section('content')
<h4>Users</h4>
<div class="card shadow-sm mb-4"><div class="card-header bg-body">Add user</div>
<form method="post" action="{{ route('users.store') }}" class="card-body row g-2">@csrf
    <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Full name" required></div>
    <div class="col-md-2"><input name="username" class="form-control form-control-sm" placeholder="Username" required></div>
    <div class="col-md-3"><input name="email" type="email" class="form-control form-control-sm" placeholder="Email" required></div>
    <div class="col-md-2"><input name="password" type="password" class="form-control form-control-sm" placeholder="Password (8+)" required></div>
    <div class="col-md-2"><select name="role" class="form-select form-select-sm"><option value="viewer">Viewer</option><option value="manager">Document Manager</option><option value="admin">Administrator</option></select></div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="can_export" value="1" class="form-check-input" id="ce"><label for="ce" class="form-check-label small">Viewer may print/download</label></div></div>
    <div class="col-12"><button class="btn btn-primary btn-sm">Create</button></div>
</form></div>

<div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Password</th><th>Role</th><th>Print/download</th><th>Active</th><th></th></tr></thead>
    @foreach ($users as $u)
    <tr><form method="post" action="{{ route('users.update', $u) }}">@csrf @method('PUT')
        <td><input name="name" value="{{ $u->name }}" class="form-control form-control-sm"></td>
        <td><input name="username" value="{{ $u->username }}" class="form-control form-control-sm"></td>
        <td><input name="email" value="{{ $u->email }}" class="form-control form-control-sm"></td>
        <td><input name="password" type="password" placeholder="unchanged" class="form-control form-control-sm"></td>
        <td><select name="role" class="form-select form-select-sm">@foreach (['viewer' => 'Viewer', 'manager' => 'Document Manager', 'admin' => 'Administrator'] as $k => $v)<option value="{{ $k }}" @selected($u->role === $k)>{{ $v }}</option>@endforeach</select></td>
        <td class="text-center"><input type="checkbox" name="can_export" value="1" class="form-check-input" @checked($u->can_export)></td>
        <td class="text-center"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($u->is_active) @disabled($u->id === auth()->id())></td>
        <td><button class="btn btn-sm btn-outline-primary">Save</button></td>
    </form></tr>
    @endforeach
</table></div></div>
@endsection
