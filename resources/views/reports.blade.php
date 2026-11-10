@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<h4 class="mb-3">Reports</h4>
@php
    $blocks = ['Documents by category' => $byCategory, 'Documents by type' => $byType, 'Documents by year' => $byYear, 'OCR status' => $byStatus, 'Documents uploaded per user' => $byUser];
@endphp
<div class="row g-3">
    @foreach ($blocks as $title => $rows)
        <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100">
            <div class="card-header bg-body">{{ $title }}</div>
            <table class="table table-sm mb-0">
                @forelse ($rows as $r)<tr><td>{{ $title === 'OCR status' ? (\App\Models\Document::STATUS_LABELS[$r->label] ?? $r->label) : $r->label }}</td><td class="text-end">{{ number_format($r->n) }}</td></tr>
                @empty <tr><td class="text-muted">No data</td></tr>@endforelse
            </table>
        </div></div>
    @endforeach
    <div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100">
        <div class="card-header bg-body">OCR quality</div>
        <table class="table table-sm mb-0">
            <tr><td>Low-confidence pages</td><td class="text-end">{{ number_format($lowPages) }}</td></tr>
            <tr><td>Failed pages</td><td class="text-end">{{ number_format($failedPages) }}</td></tr>
        </table>
    </div></div>
</div>
@endsection
