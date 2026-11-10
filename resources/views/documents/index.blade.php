@extends('layouts.app')
@section('title', 'Documents')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Documents</h4>
        <div class="d-flex gap-2">
            @can('admin')<a href="{{ route('documents.trash') }}" class="btn btn-outline-secondary btn-sm"><i
                        class="bi bi-trash"></i> Deleted @if ($trashedCount)
                        <span class="badge text-bg-danger">{{ $trashedCount }}</span>
                    @endif
            </a>@endcan
            @can('manage')
                <a href="{{ route('upload') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Upload</a>
            @endcan
        </div>
    </div>
    <form class="row g-2 mb-3">
        <div class="col-md-3"><select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select></div>
        <div class="col-md-3"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All @can('admin')
                        OCR
                    @endcan statuses</option>
                @foreach (\App\Models\Document::STATUS_LABELS as $k => $v)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                @endforeach
            </select></div>
        <div class="col-md-2"><input name="year" class="form-control form-control-sm" placeholder="Year"
                value="{{ request('year') }}"></div>
    </form>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Person/Party</th>
                        <th>Year</th>
                        <th>Pages</th>
                        @can('admin')<th>OCR</th>@endcan
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                @forelse ($documents as $d)
                    <tr>
                        <td style="max-width:280px" class="text-truncate"><a
                                href="{{ route('documents.show', $d) }}">{{ $d->original_filename }}</a></td>
                        <td>{{ $d->type?->name ?? '—' }}</td>
                        <td>{{ $d->category?->name ?? '—' }}</td>
                        <td>{{ $d->party?->name ?? '—' }}</td>
                        <td>{{ $d->year ?? '—' }}</td>
                        <td>{{ $d->page_count }}</td>
                        @can('admin')
                            <td><span class="badge text-bg-{{ $d->statusColor() }}">{{ $d->statusLabel() }}</span></td>
                        @endcan
                        <td class="text-nowrap">{{ $d->created_at->format('Y-m-d') }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('documents.show', $d) }}">View</a>
                            @can('export')
                                <a class="btn btn-sm btn-outline-secondary"
                                    href="{{ route('documents.download', $d) }}">Download</a>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank"
                                    href="{{ route('documents.print', $d) }}">Print</a>
                            @endcan
                            @can('manage')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.edit', $d) }}">Edit</a>
                                <form method="post" action="{{ route('documents.destroy', $d) }}" class="d-inline"
                                    onsubmit="return confirm('Delete this document? An administrator can still restore it.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete (can be restored)"><i
                                            class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                            @can('admin')
                                <form method="post" action="{{ route('documents.purge', $d->uuid) }}" class="d-inline"
                                    onsubmit="return confirm('PERMANENTLY delete this document and all its files? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" title="Delete permanently"><i
                                            class="bi bi-x-octagon"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No documents found.</td>
                    </tr>
                @endforelse
            </table>
        </div>
    </div>
@endsection
