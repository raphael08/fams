@extends('layouts.app')
@section('title', 'Upload document')
@section('content')
<h4>Upload Document</h4>
<form method="post" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="card shadow-sm" style="max-width:640px"><div class="card-body">
    @csrf
    <label class="form-label">Choose file</label>
    <input type="file" name="file" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
    <div class="form-text mb-3">Supported: PDF, JPG, JPEG, PNG, TIFF, TIF. OCR runs in the background; you don't have to wait.</div>
    <button class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
</div></form>
@endsection
