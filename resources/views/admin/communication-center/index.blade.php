@extends('layouts.admin')
@section('title','Communication Center')
@section('content')
<div class="admin-title"><div><small>ADMIN / COMMUNICATION CENTER</small><h1>{{ $module === 'inbox' ? 'Inbox' : 'Communication Center' }}</h1></div></div>
<div class="panel">
    <div class="panel-heading"><h2>Customer conversations</h2><span class="panel-caption">Website enquiries and meeting requests</span></div>
    <form class="module-toolbar" method="get">
        <select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach(['new','open','pending','closed'] as $option)<option value="{{ $option }}" @selected($status===$option)>{{ ucfirst($option) }}</option>@endforeach</select>
        <button class="btn" type="submit">FILTER</button>
    </form>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Received</th><th>Customer</th><th>Subject</th><th>Message</th><th>Meeting request</th><th>Status</th></tr></thead>
        <tbody>@forelse($conversations as $conversation)
            @php($meeting=$conversation->metadata['meeting']??null)
            <tr>
                <td>{{ $conversation->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                <td><b>{{ $conversation->metadata['name']??'Customer' }}</b><br><a href="mailto:{{ $conversation->contact }}">{{ $conversation->contact }}</a>@if($conversation->metadata['phone']??null)<br>{{ $conversation->metadata['phone'] }}@endif</td>
                <td>{{ $conversation->subject ?: 'Contact enquiry' }}</td>
                <td>{{ str($conversation->messages->first()?->body??'')->limit(180) }}</td>
                <td>@if($meeting)<b>{{ $meeting['date'] }}</b><br>{{ $meeting['time'] }} Europe/Dublin @else — @endif</td>
                <td>{{ ucfirst($conversation->status) }}</td>
            </tr>
        @empty<tr><td colspan="6">No conversations found.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $conversations->links() }}
</div>
@endsection
