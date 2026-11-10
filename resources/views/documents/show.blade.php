@extends('layouts.app')
@section('title', $document->original_filename)
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h5 class="mb-1">{{ $document->original_filename }}</h5>
        <span class="badge text-bg-{{ $document->statusColor() }}" id="ocrBadge">{{ $document->statusLabel() }}</span>
        <span class="text-muted small ms-2">{{ $document->page_count }} pages · {{ number_format($document->file_size / 1048576, 2) }} MB</span>
    </div>
    <div class="d-flex flex-wrap gap-1">
        @can('export')
            <a class="btn btn-sm btn-outline-primary" href="{{ route('documents.download', $document) }}"><i class="bi bi-download"></i> Download Entire Document</a>
            <a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ route('documents.print', $document) }}"><i class="bi bi-printer"></i> Print Entire Document</a>
            @if (is_file(app(\App\Services\PageFileService::class)->searchablePath($document)))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.searchable', $document) }}">Download Searchable PDF</a>
            @endif
        @endcan
        @can('manage')
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.edit', $document) }}">Edit metadata</a>
            <form method="post" action="{{ route('documents.ocr-retry', $document) }}">@csrf <button class="btn btn-sm btn-outline-secondary">Retry OCR</button></form>
            <form method="post" action="{{ route('documents.searchable.generate', $document) }}">@csrf <button class="btn btn-sm btn-outline-secondary">Generate Searchable PDF</button></form>
        @endcan
    </div>
</div>

@if ($document->ocr_status === 'failed')
    <div class="alert alert-danger">Unable to process this document. The original file has been preserved. You can retry OCR from this page.
        @if ($document->ocr_error)<div class="small mt-1">{{ $document->ocr_error }}</div>@endif</div>
@endif
<div id="progressWrap" class="mb-3 {{ $document->isBusy() ? '' : 'd-none' }}">
    <div class="small text-muted mb-1">OCR in progress…</div>
    <div class="progress"><div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: {{ $document->ocr_progress }}%">{{ $document->ocr_progress }}%</div></div>
</div>

<div class="row g-3">
    {{-- ============ Viewer ============ --}}
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-body d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="prev" title="Previous page"><i class="bi bi-chevron-left"></i></button>
                    <button class="btn btn-outline-secondary" id="next" title="Next page"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="input-group input-group-sm" style="width:150px">
                    <span class="input-group-text">Page</span>
                    <input id="pageInput" type="number" min="1" max="{{ $document->page_count }}" class="form-control">
                    <span class="input-group-text">/ {{ $document->page_count }}</span>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="zoomOut"><i class="bi bi-zoom-out"></i></button>
                    <button class="btn btn-outline-secondary" id="zoomFit">100%</button>
                    <button class="btn btn-outline-secondary" id="zoomIn"><i class="bi bi-zoom-in"></i></button>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-1">
                    @can('export')
                        <button class="btn btn-sm btn-outline-primary" id="printPage"><i class="bi bi-printer"></i> Print Page</button>
                        <button class="btn btn-sm btn-outline-primary" id="downloadPage"><i class="bi bi-download"></i> Download Page</button>
                    @endcan
                    <button class="btn btn-sm btn-outline-secondary" id="selectPage"><i class="bi bi-square"></i> Select</button>
                    <button class="btn btn-sm btn-outline-secondary" id="ocrText"><i class="bi bi-card-text"></i> OCR Text</button>
                </div>
            </div>
            {{-- Continuous scroll: every page is a placeholder that loads its image when it comes near the screen --}}
            <div id="stage" class="bg-secondary-subtle" style="height:75vh; overflow:auto; position:relative; overflow-anchor:none">
                <div id="pages"></div>
            </div>
        </div>
    </div>

    {{-- ============ Side panel ============ --}}
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-body p-0">
                <ul class="nav nav-tabs card-header-tabs px-2 pt-2 mb-0">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tSearch">Search</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tPages">Pages</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tInfo">Info</button></li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade show active" id="tSearch">
                    <label class="form-label small">Search in this document</label>
                    <div class="input-group input-group-sm mb-2">
                        <input id="q" class="form-control" placeholder='word or "exact phrase"' value="{{ $startQuery }}">
                        <button class="btn btn-primary" id="qGo">Search</button>
                    </div>
                    <div id="qCount" class="small text-muted mb-1"></div>
                    <div id="qHits" class="list-group list-group-flush" style="max-height:50vh; overflow:auto"></div>
                </div>

                <div class="tab-pane fade" id="tPages">
                    <div class="input-group input-group-sm mb-2">
                        <input id="range" class="form-control" placeholder="Range e.g. 1-5, 27, 30-35">
                        <button class="btn btn-outline-secondary" id="rangeAdd">Select range</button>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span id="selCount">Selected: 0 pages</span>
                        <a href="#" id="selClear">Clear</a>
                    </div>
                    @can('export')
                    <div class="d-flex gap-1 mb-2">
                        <button class="btn btn-sm btn-primary flex-fill" id="dlSel" disabled>Download Selected</button>
                        <button class="btn btn-sm btn-outline-primary flex-fill" id="prSel" disabled>Print Selected</button>
                    </div>
                    @endcan
                    <div style="max-height:48vh; overflow:auto" class="border rounded">
                        <table class="table table-sm table-hover mb-0 align-middle no-datatable">
                            @foreach ($pages as $p)
                                @php $low = $p->ocr_status === 'completed' && $p->ocr_confidence !== null && $p->ocr_confidence < config('archive.ocr_low_confidence'); @endphp
                                <tr data-row="{{ $p->page_number }}">
                                    <td style="width:28px"><input type="checkbox" class="form-check-input pg-check" data-p="{{ $p->page_number }}"></td>
                                    <td><a href="#" class="pg-go text-decoration-none" data-p="{{ $p->page_number }}">Page {{ $p->page_number }}</a></td>
                                    <td class="text-end small">
                                        @if ($p->ocr_status === 'completed')
                                            @if ($p->ocr_confidence !== null)<span class="text-muted">{{ round($p->ocr_confidence) }}%</span>@else<span class="text-muted">blank</span>@endif
                                            @if ($low)<span class="badge text-bg-warning">Low Confidence</span>@endif
                                        @elseif ($p->ocr_status === 'failed')<span class="badge text-bg-danger">Failed</span>
                                        @else<span class="badge text-bg-secondary">{{ ucfirst($p->ocr_status) }}</span>@endif
                                        @can('manage')
                                            @if ($low || $p->ocr_status === 'failed')
                                                <button class="btn btn-link btn-sm p-0 ms-1 pg-retry" data-p="{{ $p->page_number }}" title="Retry OCR for this page"><i class="bi bi-arrow-repeat"></i></button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tInfo">
                    <dl class="row small mb-0">
                        <dt class="col-5">Category</dt><dd class="col-7">{{ $document->category?->name ?? '—' }}</dd>
                        <dt class="col-5">Type</dt><dd class="col-7">{{ $document->type?->name ?? '—' }}</dd>
                        <dt class="col-5">Person/Party</dt><dd class="col-7">{{ $document->party?->name ?? '—' }}</dd>
                        <dt class="col-5">Year</dt><dd class="col-7">{{ $document->year ?? '—' }}</dd>
                        <dt class="col-5">Document no.</dt><dd class="col-7">{{ $document->document_number ?? '—' }}</dd>
                        <dt class="col-5">Reference no.</dt><dd class="col-7">{{ $document->reference_number ?? '—' }}</dd>
                        <dt class="col-5">Metadata</dt><dd class="col-7">{{ $document->metadata_source === 'manual' ? 'Edited manually' : 'Automatic' }}</dd>
                        <dt class="col-5">SHA-256</dt><dd class="col-7 text-break" style="font-size:.7rem">{{ $document->sha256_hash }}</dd>
                        <dt class="col-5">Uploaded</dt><dd class="col-7">{{ $document->created_at->format('Y-m-d H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ocrModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h6 class="modal-title" id="ocrTitle">OCR Text</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><pre id="ocrBody" style="white-space:pre-wrap"></pre></div>
</div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const base = @json(url('/documents/' . $document->uuid));
    const total = {{ $document->page_count }};
    const busy = {{ $document->isBusy() ? 'true' : 'false' }};
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const $ = id => document.getElementById(id);
    const stage = $('stage'), holder = $('pages');
    let page = {{ $startPage }}, zoom = 100, ratio = 1 / 1.414;   // ratio = width/height guess (A4) until a real page loads
    const selected = new Set();
    const pgs = [];

    // ---- build one placeholder per page ----
    for (let n = 1; n <= total; n++) {
        const d = document.createElement('div');
        d.className = 'pg bg-white shadow-sm d-flex align-items-center justify-content-center text-muted small';
        d.style.cssText = `width:100%; margin:8px auto; aspect-ratio:${ratio}; position:relative`;
        d.dataset.p = n; d.textContent = `Page ${n}`;
        holder.appendChild(d); pgs.push(d);
    }

    function loadPage(d) {
        if (d.dataset.state === 'loaded' || d.dataset.state === 'loading') return;
        d.dataset.state = 'loading';
        const im = new Image();
        im.alt = `Page ${d.dataset.p}`;
        im.style.cssText = 'width:100%; height:auto; display:block';
        im.onload = () => {
            const before = d.offsetHeight;
            d.textContent = ''; d.appendChild(im);
            d.style.aspectRatio = `${im.naturalWidth} / ${im.naturalHeight}`;
            d.dataset.sized = '1'; d.dataset.state = 'loaded';
            ratio = im.naturalWidth / im.naturalHeight;
            // keep what the reader is looking at in place when a page ABOVE changes height
            const delta = d.offsetHeight - before;
            if (delta && d.offsetTop + before <= stage.scrollTop + 1) stage.scrollTop += delta;
            pgs.forEach(x => { if (!x.dataset.sized) x.style.aspectRatio = ratio; });
        };
        im.onerror = () => { d.dataset.state = ''; d.textContent = 'This page could not be displayed.'; };
        im.src = `${base}/pages/${d.dataset.p}/image`;
    }
    function unloadPage(d) {                       // free memory for pages far from the screen
        if (d.dataset.state !== 'loaded') return;
        d.dataset.state = '';
        d.textContent = `Page ${d.dataset.p}`;     // aspect-ratio stays, so the layout does not move
    }

    const io = new IntersectionObserver(entries => {
        entries.forEach(en => en.isIntersecting ? loadPage(en.target) : unloadPage(en.target));
    }, { root: stage, rootMargin: '1500px 0px' });
    pgs.forEach(d => io.observe(d));

    // ---- which page is on screen? ----
    function currentFromScroll() {
        if (stage.scrollTop + stage.clientHeight >= stage.scrollHeight - 2) return pgs.length;   // at the very end = last page
        const y = stage.scrollTop + stage.clientHeight * 0.25;
        let lo = 0, hi = pgs.length - 1;
        while (lo < hi) { const mid = (lo + hi + 1) >> 1; if (pgs[mid].offsetTop <= y) lo = mid; else hi = mid - 1; }
        return lo + 1;
    }
    function setCurrent(n) {
        if (n === page && $('pageInput').value) return;
        page = n;
        if (document.activeElement !== $('pageInput')) $('pageInput').value = n;
        history.replaceState(null, '', `?page=${n}`);
        document.querySelectorAll('tr[data-row]').forEach(r => r.classList.toggle('table-active', +r.dataset.row === n));
        $('selectPage').innerHTML = `<i class="bi bi-${selected.has(n) ? 'check-square' : 'square'}"></i> Select`;
    }
    let ticking = false, quietUntil = 0;
    stage.addEventListener('scroll', () => {
        if (ticking || performance.now() < quietUntil) return; ticking = true;
        requestAnimationFrame(() => { ticking = false; setCurrent(currentFromScroll()); });
    });

    // Jump to a page (Next / Prev / page box / search hit / page list). You can still scroll freely afterwards.
    function go(n) {
        if (!total) return;
        n = Math.max(1, Math.min(total, parseInt(n) || 1));
        loadPage(pgs[n - 1]);
        quietUntil = performance.now() + 150;       // ignore the scroll event caused by this jump
        stage.scrollTop = pgs[n - 1].offsetTop - 8;
        setCurrent(n);
        $('pageInput').value = n;
    }
    function setZoom(z) {
        zoom = Math.max(50, Math.min(300, z));
        holder.style.width = zoom + '%';
        $('zoomFit').textContent = zoom + '%';
        go(page);                                   // stay on the same page after the size changes
    }

    // ---- selection ----
    function compact() {
        const a = [...selected].sort((x, y) => x - y), out = [];
        for (let i = 0; i < a.length; i++) { let j = i; while (a[j + 1] === a[j] + 1) j++; out.push(j > i ? `${a[i]}-${a[j]}` : `${a[i]}`); i = j; }
        return out.join(',');
    }
    function refreshSel() {
        $('selCount').textContent = `Selected: ${selected.size} page${selected.size === 1 ? '' : 's'}`;
        document.querySelectorAll('.pg-check').forEach(c => c.checked = selected.has(+c.dataset.p));
        ['dlSel', 'prSel'].forEach(id => $(id) && ($(id).disabled = selected.size === 0));
        $('selectPage').innerHTML = `<i class="bi bi-${selected.has(page) ? 'check-square' : 'square'}"></i> Select`;
    }
    function parseRange(s) {
        const out = [];
        for (const part of s.split(/[,\s;]+/).filter(Boolean)) {
            let m;
            if ((m = part.match(/^(\d+)-(\d+)$/))) { const a = +m[1], b = +m[2]; if (a > b) throw `Invalid range "${part}"`; for (let i = a; i <= b; i++) out.push(i); }
            else if (/^\d+$/.test(part)) out.push(+part);
            else throw `Invalid page "${part}"`;
        }
        const bad = out.find(p => p < 1 || p > total);
        if (bad) throw `Page ${bad} does not exist (document has ${total} pages).`;
        return out;
    }
    function post(action, target) {
        const f = document.createElement('form');
        f.method = 'POST'; f.action = action; f.target = target;
        f.innerHTML = `<input type="hidden" name="_token" value="${csrf}"><input type="hidden" name="pages" value="${compact()}">`;
        document.body.appendChild(f); f.submit(); f.remove();
    }

    // ---- in-document search ----
    async function search() {
        const q = $('q').value.trim(); if (!q) return;
        $('qCount').textContent = 'Searching…'; $('qHits').innerHTML = '';
        const r = await (await fetch(`${base}/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })).json();
        $('qCount').textContent = `${r.count} match${r.count === 1 ? '' : 'es'} (pages)`;
        $('qHits').innerHTML = r.hits.map(h => `<a href="#" class="list-group-item list-group-item-action py-1" data-p="${h.page}"><strong>Page ${h.page}</strong><div class="small text-muted text-truncate"></div></a>`).join('');
        [...$('qHits').children].forEach((el, i) => el.querySelector('div').textContent = r.hits[i].snippet);
        document.querySelectorAll('tr[data-row]').forEach(tr => tr.classList.remove('table-warning'));
        r.hits.forEach(h => document.querySelector(`tr[data-row="${h.page}"]`)?.classList.add('table-warning'));
    }

    // ---- wiring ----
    $('prev').onclick = () => go(page - 1); $('next').onclick = () => go(page + 1);
    $('pageInput').onchange = e => go(e.target.value);
    $('zoomIn').onclick = () => setZoom(zoom + 25); $('zoomOut').onclick = () => setZoom(zoom - 25); $('zoomFit').onclick = () => setZoom(100);
    $('printPage')?.addEventListener('click', () => window.open(`${base}/pages/${page}/print`, '_blank'));
    $('downloadPage')?.addEventListener('click', () => { location.href = `${base}/pages/${page}/download`; });
    $('selectPage').onclick = () => { selected.has(page) ? selected.delete(page) : selected.add(page); refreshSel(); };
    $('ocrText').onclick = async () => {
        const r = await (await fetch(`${base}/pages/${page}/ocr`, { headers: { Accept: 'application/json' } })).json();
        $('ocrTitle').textContent = `Page ${page} — OCR text` + (r.confidence != null ? ` (${Math.round(r.confidence)}% confidence)` : '');
        $('ocrBody').textContent = r.error ? `OCR failed: ${r.error}` : (r.text || (r.status === 'completed' ? '(no text found on this page)' : `(OCR ${r.status})`));
        new bootstrap.Modal($('ocrModal')).show();
    };
    $('qGo').onclick = search; $('q').onkeydown = e => { if (e.key === 'Enter') search(); };
    $('qHits').onclick = e => { const a = e.target.closest('[data-p]'); if (a) { e.preventDefault(); go(a.dataset.p); } };
    document.querySelectorAll('.pg-go').forEach(a => a.onclick = e => { e.preventDefault(); go(a.dataset.p); });
    document.querySelectorAll('.pg-check').forEach(c => c.onchange = () => { c.checked ? selected.add(+c.dataset.p) : selected.delete(+c.dataset.p); refreshSel(); });
    document.querySelectorAll('.pg-retry').forEach(b => b.onclick = async () => {
        await fetch(`${base}/pages/${b.dataset.p}/ocr/retry`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        location.reload();
    });
    $('rangeAdd').onclick = () => { try { parseRange($('range').value).forEach(p => selected.add(p)); refreshSel(); } catch (e) { alert(e); } };
    $('selClear').onclick = e => { e.preventDefault(); selected.clear(); refreshSel(); };
    $('dlSel')?.addEventListener('click', () => post(`${base}/pages/download-selected`, '_self'));
    $('prSel')?.addEventListener('click', () => post(`${base}/pages/print-selected`, '_blank'));
    document.addEventListener('keydown', e => {
        if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
        if (e.key === 'ArrowLeft') go(page - 1); if (e.key === 'ArrowRight') go(page + 1);
    });

    if (total) { holder.style.width = '100%'; go(page); }
    if ($('q').value) search();

    if (busy) {
        const t = setInterval(async () => {
            const s = await (await fetch(`${base}/status`, { headers: { Accept: 'application/json' } })).json();
            $('progressBar').style.width = s.progress + '%'; $('progressBar').textContent = s.progress + '%';
            if (!s.busy) { clearInterval(t); location.reload(); }
        }, 3000);
    }
})();
</script>
@endpush
