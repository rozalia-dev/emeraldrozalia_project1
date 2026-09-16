@extends('layouts.admin')

@section('title', $title)

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-resource-page]');
    if (!page) return;
    const checkboxes = [...page.querySelectorAll('[data-resource-select]')];
    const selectAll = page.querySelector('[data-resource-select-all]');
    const count = page.querySelector('[data-resource-selection-count]');
    const form = page.querySelector('#resource-bulk-form');
    const update = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (count) count.textContent = `${selected} selected`;
        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
        }
    };
    selectAll?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
        update();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));
    form?.addEventListener('submit', (event) => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (selected === 0) {
            event.preventDefault();
            window.alert('Select at least one record first.');
            return;
        }
        const action = form.querySelector('[name=action]')?.value;
        const destructive = action === 'trash' || action === 'permanent_delete';
        if (destructive && !window.confirm(action === 'permanent_delete'
            ? `Permanently delete ${selected} selected record${selected === 1 ? '' : 's'}? This cannot be undone.`
            : `Move ${selected} selected record${selected === 1 ? '' : 's'} to trash?`)) {
            event.preventDefault();
        }
    });
    update();
});
</script>
@endpush

@section('content')
<div class="admin-title">
    <div>
        <small>ADMIN / OPERATIONS</small>
        <h1>{{ $title }}</h1>
        <p class="admin-page-description">{{ $description }}</p>
    </div>
</div>

<div class="resource-page" data-resource-page data-resource-tab="{{ $tab }}">
    <nav class="resource-view-tabs" aria-label="{{ $title }} record views">
        <a class="{{ $tab === 'active' ? 'active' : '' }}" href="{{ route('admin.resource', $module) }}" @if($tab === 'active') aria-current="page" @endif>
            Active records
        </a>
        <a class="{{ $tab === 'trash' ? 'active' : '' }}" href="{{ route('admin.resource', [$module, 'tab' => 'trash']) }}" @if($tab === 'trash') aria-current="page" @endif>
            Trash
        </a>
    </nav>

    @if($tab === 'active')
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
    @else
        <section class="panel resource-trash-panel">
            <div class="panel-heading">
                <div><h2>Trash</h2><span class="panel-caption">Records remain recoverable until an administrator permanently deletes them.</span></div>
                <a class="btn" href="{{ route('admin.resource', $module) }}">Back to active</a>
            </div>
        </section>
    @endif

    <section class="panel resource-list-panel">
        <form class="module-toolbar module-filter-toolbar resource-filter-form" method="get" action="{{ route('admin.resource', $module) }}">
            @if($tab === 'trash')<input type="hidden" name="tab" value="trash">@endif
            <label>Search<input type="search" name="q" value="{{ $search }}" placeholder="Title or reference" aria-label="Search records"></label>
            <label>Status<select name="status"><option value="">All statuses</option>@foreach($statuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label>
            <label>From<input type="date" name="date_from" value="{{ $dateFrom }}"></label>
            <label>To<input type="date" name="date_to" value="{{ $dateTo }}"></label>
            <button type="submit">FILTER</button>
            @if($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')<a class="clear-filter" href="{{ route('admin.resource', $tab === 'trash' ? [$module, 'tab' => 'trash'] : $module) }}">Clear</a>@endif
        </form>

        <form id="resource-bulk-form" class="module-toolbar resource-bulk-toolbar" method="post" action="{{ route('admin.resource.bulk-actions', $module) }}">
            @csrf
            <label>Bulk action
                <select name="action" required>
                    @if($tab === 'active')
                        <option value="archive">Archive selected</option>
                        <option value="trash">Move selected to trash</option>
                    @else
                        <option value="restore">Restore selected</option>
                        <option value="permanent_delete">Permanently delete selected</option>
                    @endif
                </select>
            </label>
            <button type="submit">APPLY</button>
            <span class="panel-caption" data-resource-selection-count>0 selected</span>
        </form>

        <div class="table-wrap">
            <table class="data-table resource-table">
                <thead><tr><th><input type="checkbox" data-resource-select-all aria-label="Select all visible records"></th><th>Reference</th><th>Title</th><th>Status</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($records as $record)
                    @php
                        $notes = data_get($record->data, 'notes');
                        $actions = app(\App\Services\AdminActionRegistry::class)->for($record);
                    @endphp
                    <tr data-record-id="{{ $record->getKey() }}" data-record-status="{{ $record->status }}">
                        <td><input type="checkbox" name="ids[]" value="{{ $record->getKey() }}" form="resource-bulk-form" data-resource-select aria-label="Select {{ $record->title }}"></td>
                        <td><code>{{ $record->reference ?: '—' }}</code></td>
                        <td><strong>{{ $record->title }}</strong>@if($notes)<small class="resource-note" title="{{ $notes }}">{{ str($notes)->limit(120) }}</small>@endif</td>
                        <td><span class="resource-status resource-status--{{ str($record->status)->slug() }}">{{ str($record->status)->headline() }}</span>@if($record->trashed())<small class="resource-deleted-at">Trashed {{ $record->deleted_at?->format('d M Y H:i') }}</small>@endif</td>
                        <td>{{ $record->amount !== null ? '€'.number_format((float) $record->amount, 2) : '—' }}</td>
                        <td>{{ $record->record_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            <details class="resource-action-menu" data-admin-action-menu>
                                <summary aria-label="Actions for {{ $record->title }}">Actions <x-icon name="chevron-down" size="12" /></summary>
                                <div class="resource-action-menu-panel" role="menu">
                                    @foreach($actions as $action)
                                        @if($action['type'] === 'link')
                                            <a class="resource-action-item resource-action-item--{{ $action['tone'] }}" role="menuitem" href="{{ $action['href'] }}">{{ $action['label'] }}</a>
                                        @else
                                            <form method="post" action="{{ $action['href'] }}" data-resource-action-form="{{ $action['key'] }}" @if(isset($action['confirm'])) onsubmit="return confirm('{{ $action['confirm'] }}')" @endif>
                                                @csrf
                                                @if($action['method'] !== 'POST') @method($action['method']) @endif
                                                <button class="resource-action-item resource-action-item--{{ $action['tone'] }}" type="submit" role="menuitem">{{ $action['label'] }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-note">{{ $tab === 'trash' ? 'Trash is empty.' : 'No records found. Use Add Record above to create the first one.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    </section>
</div>
@endsection
