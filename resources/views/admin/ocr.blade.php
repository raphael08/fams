@extends('layouts.app')
@section('title', 'OCR')
@section('content')
<h4>OCR Status</h4>
<p class="text-muted">Documents that are queued, running, failed, or finished with warnings (low-confidence or failed pages).</p>

<form method="post" action="{{ route('ocr.retry-selected') }}" id="retryForm">
    @csrf
    <div class="d-flex align-items-center gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn"><i class="bi bi-check2-square"></i> Select all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectFailedBtn"><i class="bi bi-exclamation-triangle"></i> Select failed</button>
        <button type="submit" class="btn btn-sm btn-primary" id="retrySelectedBtn" disabled><i class="bi bi-arrow-repeat"></i> Retry selected (<span id="selCount">0</span>)</button>
    </div>

    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0" id="ocrTable">
        <thead><tr>
            <th class="select-col no-export" style="width:36px"><input type="checkbox" class="form-check-input" id="checkAll" title="Select / unselect all"></th>
            <th>Document</th><th>Pages</th><th>Status</th><th>Progress</th><th></th>
        </tr></thead>
        @forelse ($documents as $d)
            <tr>
                <td><input type="checkbox" class="form-check-input row-check" value="{{ $d->id }}" data-failed="{{ $d->ocr_status === 'failed' ? 1 : 0 }}"></td>
                <td><a href="{{ route('documents.show', $d) }}">{{ $d->original_filename }}</a>@if ($d->ocr_error)<div class="small text-danger">{{ $d->ocr_error }}</div>@endif</td>
                <td>{{ $d->page_count }}</td>
                <td><span class="badge text-bg-{{ $d->statusColor() }}">{{ $d->statusLabel() }}</span></td>
                <td style="width:180px"><div class="progress"><div class="progress-bar" style="width:{{ $d->ocr_progress }}%">{{ $d->ocr_progress }}%</div></div></td>
                <td class="text-end"><button type="submit" formaction="{{ route('documents.ocr-retry', $d) }}" class="btn btn-sm btn-outline-secondary">Retry</button></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Nothing to review. All OCR is complete.</td></tr>
        @endforelse
    </table></div></div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    var $table = $('#ocrTable'), form = document.getElementById('retryForm');
    var dt = $.fn.dataTable.isDataTable($table) ? $table.DataTable() : null;

    // Every row's checkbox, including rows on other pages / hidden by the search box.
    function allChecks() { return dt ? $(dt.rows().nodes()).find('.row-check') : $table.find('.row-check'); }
    function visibleChecks() { return dt ? $(dt.rows({ search: 'applied' }).nodes()).find('.row-check') : $table.find('.row-check'); }

    function refresh() {
        var total = allChecks().length, n = allChecks().filter(':checked').length;
        $('#selCount').text(n);
        $('#retrySelectedBtn').prop('disabled', n === 0);
        var vis = visibleChecks(), visChecked = vis.filter(':checked').length;
        var all = vis.length > 0 && visChecked === vis.length;
        $('#checkAll').prop('checked', all).prop('indeterminate', visChecked > 0 && !all);
        $('#selectAllBtn').html(all ? '<i class="bi bi-square"></i> Unselect all' : '<i class="bi bi-check2-square"></i> Select all');
    }

    function setAll(state) { visibleChecks().prop('checked', state); refresh(); }

    $('#checkAll').on('change', function () { setAll(this.checked); });
    $('#selectAllBtn').on('click', function () { setAll(!$('#checkAll').prop('checked')); });
    $('#selectFailedBtn').on('click', function () {
        allChecks().prop('checked', false);
        visibleChecks().filter('[data-failed="1"]').prop('checked', true);
        refresh();
    });
    $table.on('change', '.row-check', refresh);
    if (dt) { dt.on('draw', refresh); }

    // Rows on other pages are not in the DOM, so add the selected ids to the form before it is sent.
    form.addEventListener('submit', function (e) {
        var ids = allChecks().filter(':checked').map(function () { return this.value; }).get();
        var isBulk = !e.submitter || e.submitter.id === 'retrySelectedBtn';
        if (isBulk) {
            if (!ids.length) { e.preventDefault(); return; }
            if (!confirm('Retry OCR for ' + ids.length + ' selected document(s)?')) { e.preventDefault(); return; }
            ids.forEach(function (id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'ids[]'; i.value = id; form.appendChild(i);
            });
        }
    });
    refresh();
});
</script>
@endpush
