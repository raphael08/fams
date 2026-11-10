@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<h4>Settings</h4>
<p class="text-muted">Saved in the database and applied immediately (no restart, no <code>.env</code> edit). Fields you never change use the <code>.env</code> / default value.</p>

<form method="post" action="{{ route('settings.update') }}" style="max-width:760px">
    @csrf @method('PUT')
    @foreach (collect($defs)->groupBy('group') as $group => $items)
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-body fw-semibold">{{ $group }}</div>
            <div class="card-body">
                @foreach ($defs as $key => $d)
                    @continue($d['group'] !== $group)
                    <div class="row g-2 align-items-center mb-3">
                        <label class="col-md-5 col-form-label" for="s_{{ $key }}">{{ $d['label'] }}
                            <div class="form-text mt-0">{{ $d['help'] }}</div></label>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="number" step="{{ $d['type'] === 'float' ? '0.1' : '1' }}" min="{{ $d['min'] }}" max="{{ $d['max'] }}"
                                       class="form-control @error($key) is-invalid @enderror" id="s_{{ $key }}" name="{{ $key }}"
                                       value="{{ old($key, $values[$key]) }}" required>
                                <span class="input-group-text">{{ $d['unit'] }}</span>
                            </div>
                            @error($key)<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 small text-muted">default: {{ $defaults[$key] + 0 }} {{ $d['unit'] }}
                            @if (($d['php'] ?? null) === 'upload' && is_finite($phpUpload) && ($values[$key] > $phpUpload))
                                <div class="text-warning"><i class="bi bi-exclamation-triangle"></i> PHP only allows {{ round($phpUpload) }} MB per upload (<code>upload_max_filesize</code> / <code>post_max_size</code> in php.ini).</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Save settings</button>
        <button class="btn btn-outline-secondary" form="reset-form" onclick="return confirm('Reset all settings to the default values?')">Reset to defaults</button>
    </div>
</form>
<form id="reset-form" method="post" action="{{ route('settings.reset') }}">@csrf</form>

<div class="card shadow-sm mt-3" style="max-width:760px"><div class="card-body small">
    <strong>Read-only:</strong> queue driver <code>{{ config('queue.default') }}</code> &middot;
    PHP upload limit <code>{{ is_finite($phpUpload) ? round($phpUpload) . ' MB' : 'unlimited' }}</code>
</div></div>
@endsection
