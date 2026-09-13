@extends('layouts.admin')

@section('title', $title.' · '.$record->title)

@section('content')
<div class="admin-title resource-detail-title">
    <div>
        <small><a href="{{ route('admin.resource', $module) }}">{{ $title }}</a> / RECORD</small>
        <h1>{{ $record->title }}</h1>
        <p class="admin-page-description">{{ $description }}</p>
    </div>
    <a class="btn" href="{{ route('admin.resource', $module) }}">Back to {{ $title }}</a>
</div>

<div class="resource-detail-grid" data-resource-detail data-record-id="{{ $record->getKey() }}">
    <section class="panel resource-detail-card">
        <div class="panel-heading">
            <div><h2>Record details</h2><span class="panel-caption">Server-backed record data and audit-safe status.</span></div>
            <span class="resource-status resource-status--{{ str($record->status)->slug() }}">{{ str($record->status)->headline() }}</span>
        </div>
        <dl class="resource-detail-list">
            <div><dt>Reference</dt><dd><code>{{ $record->reference ?: '—' }}</code></dd></div>
            <div><dt>Amount</dt><dd>{{ $record->amount !== null ? '€'.number_format((float) $record->amount, 2) : '—' }}</dd></div>
            <div><dt>Record date</dt><dd>{{ $record->record_date?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Created</dt><dd>{{ $record->created_at?->format('d M Y H:i') ?? '—' }}</dd></div>
            <div><dt>Updated</dt><dd>{{ $record->updated_at?->format('d M Y H:i') ?? '—' }}</dd></div>
            @if($record->trashed())<div><dt>Trashed</dt><dd>{{ $record->deleted_at?->format('d M Y H:i') }}</dd></div>@endif
            <div class="resource-detail-list-wide"><dt>Notes</dt><dd>{{ $notes ?: 'No notes recorded.' }}</dd></div>
        </dl>
    </section>

    <aside class="panel resource-detail-actions-card">
        <div class="panel-heading"><div><h2>Actions</h2><span class="panel-caption">Every action submits to a named server route.</span></div></div>
        <div class="resource-detail-action-list">
            @foreach($actions as $action)
                @if($action['type'] === 'link' && $action['key'] === 'view')
                    <span class="resource-action-item resource-action-item--muted">{{ $action['label'] }}</span>
                @elseif($action['type'] === 'link')
                    <a class="resource-action-item resource-action-item--{{ $action['tone'] }}" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                @else
                    <form method="post" action="{{ $action['href'] }}" data-resource-action-form="{{ $action['key'] }}" @if(isset($action['confirm'])) onsubmit="return confirm('{{ $action['confirm'] }}')" @endif>
                        @csrf
                        @if($action['method'] !== 'POST') @method($action['method']) @endif
                        <button class="resource-action-item resource-action-item--{{ $action['tone'] }}" type="submit">{{ $action['label'] }}</button>
                    </form>
                @endif
            @endforeach
        </div>
        <a class="resource-audit-link" href="{{ route('admin.user-system.activity') }}">Open activity &amp; security log</a>
    </aside>

    @if(! $record->trashed())
        <section class="panel resource-editor-card">
            <div class="panel-heading"><div><h2>View &amp; edit</h2><span class="panel-caption">Changes are validated, scoped to this module and recorded in the audit log.</span></div></div>
            <form method="post" action="{{ route('admin.resource.update', [$module, $record]) }}" class="resource-detail-form">
                @csrf
                @method('PATCH')
                <label class="resource-field resource-field-wide">Title / name<input name="title" value="{{ old('title', $record->title) }}" required maxlength="180"></label>
                <label class="resource-field">Reference<input name="reference" value="{{ old('reference', $record->reference) }}" maxlength="100"></label>
                <label class="resource-field">Status<select name="status" required>@foreach($statuses as $option)<option value="{{ $option }}" @selected(old('status', $record->status) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label>
                <label class="resource-field">Amount<input name="amount" value="{{ old('amount', $record->amount) }}" type="number" min="0" step="0.01"></label>
                <label class="resource-field">Date<input name="record_date" value="{{ old('record_date', $record->record_date?->format('Y-m-d')) }}" type="date"></label>
                <label class="resource-field resource-field-wide">Notes<textarea name="notes" rows="5" maxlength="3000">{{ old('notes', $notes) }}</textarea></label>
                <div class="resource-dialog-actions"><a class="btn btn-muted" href="{{ route('admin.resource', $module) }}">Cancel</a><button class="btn" type="submit">SAVE CHANGES</button></div>
            </form>
        </section>
    @endif
</div>
@endsection
