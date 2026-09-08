@extends('layouts.admin')

@section('title', $module === 'inbox' ? 'Inbox' : 'Communication Center')

@section('content')
<div class="admin-title">
    <div>
        <small>ADMIN / COMMUNICATION CENTER</small>
        <h1>{{ $module === 'inbox' ? 'Inbox' : 'Communication Center' }}</h1>
        <p class="admin-page-description">Every public enquiry, meeting request and team reply is recorded here.</p>
    </div>
</div>

<section class="panel communication-panel">
    <div class="panel-heading"><div><h2>Customer conversations</h2><span class="panel-caption">Website enquiries and meeting requests</span></div><span class="resource-record-count">{{ number_format($conversations->total()) }} conversation{{ $conversations->total() === 1 ? '' : 's' }}</span></div>
    <form class="module-toolbar module-filter-toolbar communication-filter-form" method="get">
        <label>Search<input name="q" value="{{ $search }}" type="search" placeholder="Email or subject" aria-label="Search conversations"></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['new','open','pending','closed'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach</select></label>
        <button class="btn" type="submit">FILTER</button>
        @if($search !== '' || $status !== '')<a class="clear-filter" href="{{ route('admin.resource', $module) }}">Clear</a>@endif
    </form>
    <div class="table-wrap"><table class="data-table communication-table">
        <thead><tr><th>Received</th><th>Customer</th><th>Subject</th><th>Message thread</th><th>Meeting request</th><th>Workflow</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($conversations as $conversation)
            @php($meeting = $conversation->metadata['meeting'] ?? null)
            @php($lastMessage = $conversation->messages->last())
            <tr>
                <td>{{ $conversation->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                <td><b>{{ $conversation->metadata['name'] ?? 'Customer' }}</b><br><a href="mailto:{{ $conversation->contact }}">{{ $conversation->contact }}</a>@if($conversation->metadata['phone'] ?? null)<br>{{ $conversation->metadata['phone'] }}@endif</td>
                <td>{{ $conversation->subject ?: 'Contact enquiry' }}<small>{{ ucfirst($conversation->channel) }} · {{ ucfirst($conversation->priority) }} priority</small></td>
                <td><strong>{{ $conversation->messages->count() }} message{{ $conversation->messages->count() === 1 ? '' : 's' }}</strong><small>{{ str($lastMessage?->body ?? 'No message yet')->limit(180) }}</small></td>
                <td>@if($meeting)<b>{{ $meeting['date'] }}</b><br>{{ $meeting['time'] }} Europe/Dublin @else — @endif</td>
                <td>
                    <form class="communication-status-form" method="post" action="{{ route('admin.communication.update', $conversation) }}">
                        @csrf @method('PATCH')
                        <select name="status" aria-label="Status for {{ $conversation->subject ?: $conversation->contact }}"><option value="new" @selected($conversation->status === 'new')>New</option><option value="open" @selected($conversation->status === 'open')>Open</option><option value="pending" @selected($conversation->status === 'pending')>Pending</option><option value="closed" @selected($conversation->status === 'closed')>Closed</option></select>
                        <select name="priority" aria-label="Priority for {{ $conversation->subject ?: $conversation->contact }}"><option value="low" @selected($conversation->priority === 'low')>Low</option><option value="normal" @selected($conversation->priority === 'normal')>Normal</option><option value="high" @selected($conversation->priority === 'high')>High</option><option value="urgent" @selected($conversation->priority === 'urgent')>Urgent</option></select>
                        <select name="assigned_to" aria-label="Assign conversation"><option value="">Unassigned</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected($conversation->assigned_to === $admin->id)>{{ $admin->name }}</option>@endforeach</select>
                        <input type="datetime-local" name="follow_up_at" value="{{ $conversation->follow_up_at?->format('Y-m-d\TH:i') }}" aria-label="Follow-up time">
                        <button type="submit">SAVE</button>
                    </form>
                </td>
                <td>
                    <details class="communication-reply">
                        <summary>Reply</summary>
                        <form method="post" action="{{ route('admin.communication.message.store', $conversation) }}">
                            @csrf
                            <textarea name="body" rows="4" maxlength="5000" placeholder="Write an internal reply or customer response..." required></textarea>
                            <button class="btn" type="submit">SAVE REPLY</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-note">No conversations found. Public enquiries will appear here automatically.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $conversations->links() }}
</section>
@endsection
