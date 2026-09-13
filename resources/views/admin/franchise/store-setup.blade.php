@extends('layouts.admin')

@section('title', 'Store Setup')

@push('styles')
<link rel="stylesheet" href="/css/franchise-management.css?v=20260910-1">
<link rel="stylesheet" href="/css/franchise-batch13.css?v=20260913-batch13">
@endpush

@section('content')
<script>document.body.classList.add('franchise-management-page')</script>
<div class="fm-page fm-store-setup-page">
    <section class="fm-frame">
        <header class="fm-header">
            <div>
                <div class="fm-kicker">Project 1 Control Panel (cPanel) <span>›</span> Franchise Management</div>
                <h1>Store Setup</h1>
                <p>Coordinate the checklist from approved franchise application to retail store go-live.</p>
            </div>
            <div class="fm-header-right">
                <div class="fm-breadcrumb">Home <span>›</span> Franchise Management <span>›</span> <b>Store Setup</b></div>
                <div class="fm-date-card"><x-icon name="calendar" size="22" /><span><small>Today</small><strong>{{ now()->format('l, d F Y') }}</strong><b>{{ now()->format('h:i A') }}</b></span></div>
            </div>
        </header>

        <div class="fm-metrics fm-dashboard-metrics">
            @foreach($metrics as $metric)
                @php
                    $metricQuery = match($metric['label']) {
                        'In Progress' => ['status' => 'in-progress'],
                        'Blocked' => ['status' => 'blocked'],
                        'Complete' => ['status' => 'complete'],
                        'Trash' => ['view' => 'trash'],
                        default => [],
                    };
                @endphp
                <a class="fm-metric fm-metric-link" href="{{ route('admin.franchise.store-setup', $metricQuery) }}" aria-label="Open {{ strtolower($metric['label']) }}: {{ number_format($metric['value']) }}">
                    <span class="fm-metric-icon tone-{{ $metric['tone'] }}"><x-icon name="{{ $metric['icon'] }}" size="24" /></span>
                    <div><small>{{ strtoupper($metric['label']) }}</small><strong>{{ number_format($metric['value']) }}</strong><em>{{ $metric['sub'] }}</em></div>
                </a>
            @endforeach
        </div>

        <div class="fm-content-grid">
            <main class="fm-main-panel">
                <section class="fm-table-card fm-setup-form-card" id="store-setup-form">
                    @php
                        $editingData = $editing?->data ?? [];
                        $selectedApplication = old('application_uuid', $editing?->application?->uuid);
                        $selectedStatus = old('status', $editing?->status ?? 'pending');
                        $checklistItems = ['territory_approved' => 'Territory approved', 'agreement_signed' => 'Agreement signed', 'training_complete' => 'Training complete', 'premises_ready' => 'Premises ready', 'opening_order_ready' => 'Opening order ready', 'launch_approved' => 'Launch approved'];
                    @endphp
                    <header class="fm-setup-form-heading">
                        <div><small>{{ $editing ? 'EDIT STORE SETUP' : 'NEW STORE SETUP' }}</small><h2>{{ $editing ? 'Update readiness milestone' : 'Add readiness milestone' }}</h2><p>Save operational data here so the dashboard can report progress from the same source.</p></div>
                        @if($editing)<a class="fm-secondary" href="{{ route('admin.franchise.store-setup') }}">New milestone</a>@endif
                    </header>
                    <form method="post" action="{{ $editing ? route('admin.franchise.store-setup.update', ['milestone' => $editing->uuid]) : route('admin.franchise.store-setup.store') }}" class="fm-setup-form">
                        @csrf
                        @if($editing) @method('PATCH') @endif
                        <div class="fm-form-grid">
                            <label>Franchise Application
                                <select name="application_uuid" required>
                                    <option value="">Select an application</option>
                                    @foreach($applications as $application)
                                        <option value="{{ $application->uuid }}" @selected($selectedApplication === $application->uuid)>{{ $application->applicant_name }} · {{ $application->territory }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Store Name<input name="store_name" value="{{ old('store_name', data_get($editingData, 'store_name')) }}" required maxlength="180"></label>
                            <label>Store Code<input name="store_code" value="{{ old('store_code', data_get($editingData, 'store_code')) }}" maxlength="100" placeholder="Required before activation"></label>
                            <label>Territory<input name="territory" value="{{ old('territory', data_get($editingData, 'territory', $editing?->application?->territory)) }}" required maxlength="180"></label>
                            <label>Owner / Franchisee<input name="owner_name" value="{{ old('owner_name', data_get($editingData, 'owner_name')) }}" maxlength="180"></label>
                            <label>Store Manager<input name="manager_name" value="{{ old('manager_name', data_get($editingData, 'manager_name')) }}" maxlength="180"></label>
                            <label>Due Date<input name="due_on" value="{{ old('due_on', $editing?->due_on?->format('Y-m-d')) }}" type="date"></label>
                            <label>Status
                                <select name="status" required>
                                    @foreach($statuses as $statusKey => $statusLabel)<option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>{{ $statusLabel }}</option>@endforeach
                                </select>
                            </label>
                            <label>Completed Date<input name="completed_on" value="{{ old('completed_on', $editing?->completed_on?->format('Y-m-d')) }}" type="date"></label>
                            <label class="fm-form-span">Evidence Reference<input name="evidence_reference" value="{{ old('evidence_reference', data_get($editingData, 'evidence_reference')) }}" maxlength="500" placeholder="Document, approval, or evidence reference"></label>
                            <fieldset class="fm-checklist fm-form-span">
                                <legend>Setup checklist</legend>
                                <div>
                                    @foreach($checklistItems as $key => $label)
                                        <label><input type="hidden" name="checklist[{{ $key }}]" value="0"><input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key, data_get($editingData, 'checklist.'.$key, false)))><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <label class="fm-form-span">Notes<textarea name="notes" rows="3" maxlength="3000">{{ old('notes', data_get($editingData, 'notes')) }}</textarea></label>
                        </div>
                        <footer class="fm-dialog-footer"><a class="fm-secondary" href="{{ route('admin.franchise.store-setup') }}">Clear</a><button type="submit" class="fm-primary">{{ $editing ? 'Save Changes' : 'Create Store Setup' }}</button></footer>
                    </form>
                </section>

                <section class="fm-table-card">
                    <div class="fm-tabs-row">
                        <nav class="fm-tabs" aria-label="Store Setup views">
                            <a class="{{ !$trashed ? 'active' : '' }}" href="{{ route('admin.franchise.store-setup') }}">Active Setups</a>
                            <a class="{{ $trashed ? 'active' : '' }}" href="{{ route('admin.franchise.store-setup', ['view' => 'trash']) }}">Trash</a>
                        </nav>
                        <div class="fm-toolbar">
                            <form method="get" action="{{ route('admin.franchise.store-setup') }}" class="fm-search-form">
                                @if($trashed)<input type="hidden" name="view" value="trash">@endif
                                <label class="fm-search"><input type="search" name="q" value="{{ $search }}" placeholder="Search store or application..." aria-label="Search Store Setup"></label>
                                <select name="status" aria-label="Filter by status" onchange="this.form.submit()">
                                    <option value="">All statuses</option>
                                    @foreach($statuses as $statusKey => $statusLabel)<option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>@endforeach
                                </select>
                                <button type="submit" class="fm-filter"><x-icon name="filter" size="14" /> Filter</button>
                            </form>
                            @if(!$trashed)<a class="fm-primary" href="#store-setup-form"><x-icon name="plus" size="14" /> Add Store Setup</a>@endif
                        </div>
                    </div>

                    <div class="fm-table-wrap">
                        <table class="fm-table fm-store-setup-table">
                            <thead><tr><th>SETUP ID</th><th>STORE / APPLICATION</th><th>TERRITORY</th><th>STATUS</th><th>PROGRESS</th><th>DUE DATE</th><th>OWNER</th><th class="fm-action-head">ACTION</th></tr></thead>
                            <tbody>
                            @forelse($records as $row)
                                <tr>
                                    <td><code>SETUP-{{ strtoupper(substr($row['uuid'], 0, 8)) }}</code></td>
                                    <td><strong>{{ $row['store_name'] }}</strong><small>{{ $row['store_code'] }} · {{ $row['application'] }}</small></td>
                                    <td>{{ $row['territory'] }}</td>
                                    <td><span class="fm-status fm-status-{{ str($row['status'])->slug() }}">{{ $row['status_label'] }}</span></td>
                                    <td><div class="fm-progress"><span><i style="width: {{ $row['progress'] }}%"></i></span><b>{{ $row['progress_label'] }}</b></div></td>
                                    <td class="{{ $row['overdue'] ? 'fm-overdue' : '' }}">{{ $row['due_on'] }}</td>
                                    <td>{{ $row['owner_name'] }}</td>
                                    <td class="fm-actions">
                                        @if($trashed)
                                            <form method="post" action="{{ route('admin.franchise.store-setup.restore', ['milestone' => $row['uuid']]) }}">@csrf<button type="submit" title="Restore"><x-icon name="refresh" size="15" /></button></form>
                                        @else
                                            <details class="fm-row-menu">
                                                <summary title="Store Setup actions" aria-label="Store Setup actions"><x-icon name="dots" size="15" /></summary>
                                                <div>
                                                    <a href="{{ route('admin.franchise.store-setup.edit', ['milestone' => $row['uuid']]) }}">Edit milestone</a>
                                                    @foreach($row['actions'] as $action => $label)
                                                        <form method="post" action="{{ route('admin.franchise.store-setup.action', ['milestone' => $row['uuid'], 'action' => $action]) }}" onsubmit="return confirm('{{ $label }} this milestone?')">@csrf<button type="submit">{{ $label }}</button></form>
                                                    @endforeach
                                                    <form method="post" action="{{ route('admin.franchise.store-setup.trash', ['milestone' => $row['uuid']]) }}" onsubmit="return confirm('Move this Store Setup milestone to trash?')">@csrf @method('DELETE')<button type="submit" class="is-danger">Move to trash</button></form>
                                                </div>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="fm-empty">{{ $trashed ? 'Trash is empty. Trashed Store Setup milestones can be restored from this tab.' : 'No Store Setup milestones match these filters. Add the first checklist above or open Applications & Leads to create a source application.' }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <footer class="fm-table-footer"><span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} results</span>{{ $records->links() }}</footer>
                </section>
            </main>

            <aside class="fm-side-rail">
                <section class="fm-side-card fm-purpose">
                    <h2><x-icon name="help" size="17" /> PAGE PURPOSE</h2>
                    <p>Store Setup is the operational bridge between an approved franchise application and a live retail store. Each milestone links to the application, owner, checklist, evidence and go-live decision.</p>
                </section>
                <section class="fm-side-card">
                    <h2><x-icon name="briefcase" size="17" /> KEY FEATURES</h2>
                    <ul class="fm-dashboard-links">
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-applications']) }}">Applications &amp; Leads <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-territories']) }}">Territories <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-agreements']) }}">Agreements <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'training-documents']) }}">Training &amp; Documents <x-icon name="arrow-right" size="12" /></a></li>
                        <li><a href="{{ route('admin.franchise.page', ['section' => 'franchise-retail-stores']) }}">Retail Stores <x-icon name="arrow-right" size="12" /></a></li>
                    </ul>
                </section>
                <section class="fm-side-card">
                    <h2>CHECKLIST RULE</h2>
                    <p>A milestone can move to Complete only after all six checklist items are checked. Blocked work can be reopened and every trash operation remains recoverable.</p>
                </section>
            </aside>
        </div>
    </section>
</div>
@endsection
