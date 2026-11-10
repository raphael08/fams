<!doctype html>
<html><head><meta charset="utf-8"><title>Print — {{ $document->original_filename }}</title>
<style>
    body { margin: 0; font-family: sans-serif; }
    .bar { padding: 8px 12px; background: #eee; }
    img { display: block; width: 100%; max-height: 100vh; object-fit: contain; page-break-after: always; }
    @page { margin: 8mm; }
    @media print { .bar { display: none; } }
</style></head>
<body>
<div class="bar">Printing {{ count($pages) }} page(s) — <button onclick="window.print()">Print again</button></div>
@foreach ($pages as $n)
    <img src="{{ route('pages.image', [$document, $n]) }}" alt="Page {{ $n }}">
@endforeach
<script>
    const imgs = [...document.images];
    Promise.all(imgs.map(i => i.complete ? 1 : new Promise(r => { i.onload = i.onerror = r; }))).then(() => setTimeout(() => window.print(), 300));
</script>
</body></html>
