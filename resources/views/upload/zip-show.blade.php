@extends('layouts.app')
@section('title', 'ZIP import progress')
@section('content')
<h4>ZIP IMPORT</h4>
<p class="text-muted">{{ $zip->original_filename }}</p>
<div class="card shadow-sm mb-3" style="max-width:520px"><div class="card-body">
    <div class="progress mb-3"><div id="bar" class="progress-bar" style="width:0%"></div></div>
    <table class="table table-sm mb-2">
        <tr><td>Total</td><td id="total" class="text-end">{{ $zip->total_files }}</td></tr>
        <tr><td>Processed</td><td id="processed" class="text-end">{{ $zip->processed_files }}</td></tr>
        <tr><td>Imported</td><td id="imported" class="text-end">{{ $zip->imported_files }}</td></tr>
        <tr><td>Duplicates</td><td id="duplicates" class="text-end">{{ $zip->duplicate_files }}</td></tr>
        <tr><td>Unsupported</td><td id="unsupported" class="text-end">{{ $zip->unsupported_files }}</td></tr>
        <tr><td>Failed</td><td id="failed" class="text-end">{{ $zip->failed_files }}</td></tr>
    </table>
    <div>Status: <strong id="status">{{ $zip->statusLabel() }}</strong></div>
    <div id="message" class="text-danger small">{{ $zip->message }}</div>
</div></div>

@if ($zip->isFinished())
<div class="card shadow-sm"><div class="card-header bg-body">File report</div>
<div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>File</th><th>Result</th><th>Details</th></tr></thead>
    @foreach ($report as $r)
        <tr><td class="text-break">{{ $r['name'] }}</td>
            <td><span class="badge text-bg-{{ ['imported' => 'success', 'duplicate' => 'warning', 'unsupported' => 'secondary', 'failed' => 'danger'][$r['status']] ?? 'secondary' }}">{{ ucfirst($r['status']) }}</span></td>
            <td class="small">{{ $r['message'] }}</td></tr>
    @endforeach
</table></div></div>
<a href="{{ route('documents.index') }}" class="btn btn-primary mt-3">Go to documents</a>
@endif
@endsection

@push('scripts')
@if (!$zip->isFinished())
<script>
    const url = @json(route('zip.status', $zip));
    const set = (id, v) => document.getElementById(id).textContent = v;
    async function poll() {
        const s = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
        ['total', 'processed', 'imported', 'duplicates', 'unsupported', 'failed'].forEach(k => set(k, s[k]));
        set('status', s.label); set('message', s.message || '');
        document.getElementById('bar').style.width = (s.total ? Math.round(s.processed * 100 / s.total) : 0) + '%';
        if (s.finished) location.reload(); else setTimeout(poll, 2000);
    }
    poll();
</script>
@endif
@endpush
