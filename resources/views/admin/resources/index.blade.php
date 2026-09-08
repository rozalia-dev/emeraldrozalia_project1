@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="admin-title">
    <div>
        <small>ADMIN / OPERATIONS</small>
        <h1>{{ $title }}</h1>
        <p class="admin-page-description">{{ $description }}</p>
    </div>
</div>

<div class="resource-page" data-resource-page>
    <section class="panel resource-create-panel">
        <div class="panel-heading">
            <div><h2>Add Record</h2><span class="panel-caption">Save a tracked operational record with an optional amount, date and note.</span></div>
            <span class="resource-record-count">{{ number_format($records->total()) }} record{{ $records->total() === 1 ? '' : 's' }}</span>
        </div>
        <form class="module-toolbar resource-create-form" method="post" action="{{ route('admin.resource.store', $module) }}">
            @csrf
            <label class="resource-field resource-field-wide">Title / name<input name="title" value="{{ old('title') }}" placeholder="Title / name" required maxlength="180"></label>
            <label class="resource-field">Reference<input name="reference" value="{{ old('reference') }}" placeholder="Reference" maxlength="100"></label>
            <label class="resource-field">Status<select name="status" required>@foreach($statuses as $option)<option value="{{ $option }}" @selected(old('status', 'active') === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label>
            <label class="resource-field">Amount<input name="amount" value="{{ old('amount') }}" type="number" min="0" step="0.01" placeholder="Amount"></label>
            <label class="resource-field">Date<input name="record_date" value="{{ old('record_date') }}" type="date"></label>
            <label class="resource-field resource-field-wide">Notes<input name="notes" value="{{ old('notes') }}" placeholder="Notes" maxlength="3000"></label>
            <button class="btn resource-add-button" type="submit">ADD</button>
        </form>
    </section>

    <section class="panel resource-list-panel">
        <form class="module-toolbar module-filter-toolbar resource-filter-form" method="get" action="{{ route('admin.resource', $module) }}">
            <label>Search<input type="search" name="q" value="{{ $search }}" placeholder="Title or reference" aria-label="Search records"></label>
            <label>Status<select name="status"><option value="">All statuses</option>@foreach($statuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label>
            <label>From<input type="date" name="date_from" value="{{ $dateFrom }}"></label>
            <label>To<input type="date" name="date_to" value="{{ $dateTo }}"></label>
            <button type="submit">FILTER</button>
            @if($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')<a class="clear-filter" href="{{ route('admin.resource', $module) }}">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="data-table resource-table">
                <thead><tr><th>Reference</th><th>Title</th><th>Status</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($records as $record)
                    @php
                        $notes = data_get($record->data, 'notes');
                        $payload = ['id' => $record->id, 'title' => $record->title, 'reference' => $record->reference, 'status' => $record->status, 'amount' => $record->amount, 'record_date' => $record->record_date?->format('Y-m-d'), 'notes' => $notes];
                    @endphp
                    <tr>
                        <td><code>{{ $record->reference ?: '—' }}</code></td>
                        <td><strong>{{ $record->title }}</strong>@if($notes)<small class="resource-note" title="{{ $notes }}">{{ str($notes)->limit(120) }}</small>@endif</td>
                        <td><span class="resource-status resource-status--{{ str($record->status)->slug() }}">{{ str($record->status)->headline() }}</span></td>
                        <td>{{ $record->amount !== null ? '€'.number_format((float) $record->amount, 2) : '—' }}</td>
                        <td>{{ $record->record_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            <div class="resource-actions">
                                <button type="button" class="resource-edit-button" data-resource-edit="{{ base64_encode(json_encode($payload)) }}">Edit</button>
                                <form method="post" action="{{ route('admin.resource.destroy', [$module, $record]) }}" onsubmit="return confirm('Delete this record?')">@csrf @method('DELETE')<button type="submit">Delete</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-note">No records found. Use Add Record above to create the first one.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    </section>

    <dialog class="resource-edit-dialog" data-resource-dialog>
        <form method="post" class="resource-edit-form" data-resource-edit-form data-resource-update-base="{{ route('admin.resource.update', [$module, '__record__']) }}" action="#">
            @csrf
            @method('PATCH')
            <div class="resource-dialog-heading"><div><small>EDIT RECORD</small><h2>Update record</h2></div><button type="button" class="resource-dialog-close" data-resource-dialog-close aria-label="Close edit dialog">&times;</button></div>
            <label>Title / name<input name="title" data-resource-field="title" required maxlength="180"></label>
            <label>Reference<input name="reference" data-resource-field="reference" maxlength="100"></label>
            <label>Status<select name="status" data-resource-field="status" required>@foreach($statuses as $option)<option value="{{ $option }}">{{ str($option)->headline() }}</option>@endforeach</select></label>
            <label>Amount<input name="amount" data-resource-field="amount" type="number" min="0" step="0.01"></label>
            <label>Date<input name="record_date" data-resource-field="record_date" type="date"></label>
            <label>Notes<textarea name="notes" data-resource-field="notes" rows="4" maxlength="3000"></textarea></label>
            <div class="resource-dialog-actions"><button type="button" data-resource-dialog-close>Cancel</button><button class="btn" type="submit">SAVE CHANGES</button></div>
        </form>
    </dialog>
</div>
@endsection
