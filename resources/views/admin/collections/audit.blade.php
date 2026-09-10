@extends('layouts.admin')

@section('title', 'Collection Audit Log')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/collections-admin.css') }}?v=20260910">
<link rel="stylesheet" href="{{ asset('css/collections-admin-fixes.css') }}?v=20260910-2">
@endpush

@section('content')
<div class="collections-screen collection-audit-page">
    <div class="collection-audit-head">
        <div>
            <p class="collections-eyebrow">UUID TRACEABILITY / AUDIT HISTORY</p>
            <h1>Collection Audit Log</h1>
            <p>{{ $collection->name }} · <code>{{ $collection->public_uuid }}</code></p>
        </div>
        <a class="collections-outline-button" href="{{ route('admin.collections.index', ['selected' => $collection->id]) }}">← Back to Collections</a>
    </div>

    <div class="collection-audit-list">
        @forelse($logs as $log)
            <article class="collection-audit-item">
                <header><strong>{{ str($log->action)->replace('.', ' → ')->headline() }}</strong><time>{{ $log->created_at?->format('d M Y h:i:s A') }}</time></header>
                <p>Request UUID: <code>{{ $log->request_id ?: $log->uuid }}</code>@if($log->ip_address) · IP {{ $log->ip_address }}@endif</p>
                @if($log->before)<details><summary>Before</summary><pre>{{ json_encode($log->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
                @if($log->after)<details><summary>After</summary><pre>{{ json_encode($log->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
            </article>
        @empty
            <div class="collections-empty"><x-icon name="file-text" size="28" /><strong>No audit events yet.</strong><span>Collection changes will appear here with request and UUID traceability.</span></div>
        @endforelse
    </div>

    <div class="collection-audit-pagination">{{ $logs->links() }}</div>
</div>
@endsection
