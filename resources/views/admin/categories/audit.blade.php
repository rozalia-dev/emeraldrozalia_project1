@extends('layouts.admin')

@section('title', 'Category Audit Log')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-categories.css') }}?v=20260910-1">
@endpush

@section('content')
<div class="cat-audit-page">
    <div class="cat-audit-head">
        <div>
            <h1>Category Audit Log</h1>
            <p>{{ $category->name }} · <code>{{ $category->public_uuid }}</code></p>
        </div>
        <a class="cat-btn" href="{{ route('admin.categories.index', ['selected' => $category->public_uuid]) }}">← Back to Categories</a>
    </div>

    <div class="cat-audit-list">
        @forelse($logs as $log)
            <article class="cat-audit-item">
                <header><strong>{{ str($log->action)->replace('.', ' → ')->headline() }}</strong><time>{{ $log->created_at?->format('d M Y h:i:s A') }}</time></header>
                <p>Request UUID: <code>{{ $log->request_id ?: $log->uuid }}</code>@if($log->ip_address) · IP {{ $log->ip_address }}@endif</p>
                @if($log->before)<details><summary>Before</summary><pre>{{ json_encode($log->before, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
                @if($log->after)<details><summary>After</summary><pre>{{ json_encode($log->after, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
            </article>
        @empty
            <div class="cat-empty"><x-icon name="file-text" size="28" /><strong>No audit events yet.</strong><span>Changes made through Category Management will appear here.</span></div>
        @endforelse
    </div>

    <div class="cat-pagination-row">{{ $logs->links() }}</div>
</div>
@endsection
