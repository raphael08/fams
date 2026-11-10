<!doctype html>
<html><head><meta charset="utf-8"><title>Print — {{ $document->original_filename }}</title>
<style>body{margin:0;font-family:sans-serif} .bar{padding:8px 12px;background:#eee} iframe{width:100%;height:calc(100vh - 44px);border:0}</style></head>
<body>
<div class="bar">
    <button onclick="document.getElementById('f').contentWindow.print()">Print entire document</button>
    &nbsp; {{ $document->original_filename }} ({{ $document->page_count }} pages) —
    <a href="{{ route('documents.download', [$document, 'inline' => 1]) }}" target="_blank">open in new tab</a>
</div>
<iframe id="f" src="{{ route('documents.download', [$document, 'inline' => 1]) }}"></iframe>
</body></html>
