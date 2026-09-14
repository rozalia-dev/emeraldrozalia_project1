@extends('layouts.admin')

@section('title', 'Quote '.$quote->uuid)

@section('content')
@php
    $money = static fn ($value): string => '€'.number_format((float) $value, 2);
    $inquiry = $quote->inquiry;
    $lineItems = is_array($quote->line_items) ? $quote->line_items : [];
@endphp
<div class="admin-page-shell" data-sales-quote-show>
    <div class="admin-page-heading">
        <div>
            <p class="admin-eyebrow"><a href="{{ route('admin.quotes.index') }}">Sales Quotes</a> / {{ str($quote->order_type)->headline() }}</p>
            <h1>Quote {{ $quote->uuid }}</h1>
            <p>Version {{ $quote->version }} · {{ str($quote->status)->replace('_', ' ')->headline() }} · {{ optional($quote->submitted_at)->format('d M Y H:i') }}</p>
        </div>
        <a class="admin-button admin-button--secondary" href="{{ route('admin.quotes.index') }}">Back to queue</a>
    </div>

    @if(session('success'))
        <div class="admin-alert admin-alert--success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert--error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="admin-detail-grid">
        <section class="admin-card">
            <div class="admin-card-heading"><div><h2>Requester and source</h2><p>One enquiry, one conversation, one quote and one eventual shared order.</p></div></div>
            <dl class="admin-detail-list">
                <div><dt>Requester</dt><dd>{{ $inquiry?->name ?: 'Not supplied' }}</dd></div>
                <div><dt>Company</dt><dd>{{ $inquiry?->company ?: 'Not supplied' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $inquiry?->email ?: 'Not supplied' }}</dd></div>
                <div><dt>Enquiry type</dt><dd>{{ $inquiry?->type ?: 'Not supplied' }}</dd></div>
                <div><dt>Correlation ID</dt><dd>{{ $quote->correlation_id ?: 'Not supplied' }}</dd></div>
                <div><dt>Conversation</dt><dd>{{ $quote->conversation?->uuid ?: 'Not linked' }}</dd></div>
            </dl>
            @if($inquiry?->message)<blockquote>{{ $inquiry->message }}</blockquote>@endif
        </section>

        <section class="admin-card">
            <div class="admin-card-heading"><div><h2>Lifecycle</h2><p>State changes are versioned and audited.</p></div></div>
            <dl class="admin-detail-list">
                <div><dt>Quote status</dt><dd>{{ str($quote->status)->replace('_', ' ')->headline() }}</dd></div>
                <div><dt>Order type</dt><dd>{{ str($quote->order_type)->headline() }}</dd></div>
                <div><dt>Currency</dt><dd>{{ $quote->currency_code }} · {{ $quote->exchange_rate }}</dd></div>
                <div><dt>Subtotal</dt><dd>{{ $money($quote->subtotal) }}</dd></div>
                <div><dt>Shipping</dt><dd>{{ $money($quote->shipping) }}</dd></div>
                <div><dt>Discount</dt><dd>{{ $money($quote->discount) }}</dd></div>
                <div><dt>Total</dt><dd><strong>{{ $money($quote->total) }}</strong></dd></div>
                <div><dt>Converted order</dt><dd>@if($quote->order)<a href="{{ route('admin.order-master.show', [$quote->order->order_type, $quote->order]) }}">{{ $quote->order->number }}</a>@else Not converted @endif</dd></div>
            </dl>
        </section>
    </div>

    <section class="admin-card">
        <div class="admin-card-heading"><div><h2>Pricing and line items</h2><p>Use product and variant IDs from the live catalogue. Negotiated unit prices are persisted in the quote snapshot.</p></div></div>
        <form method="post" action="{{ route('admin.quotes.update', $quote) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_version" value="{{ $quote->version }}">
            <label>Line items JSON
                <textarea name="line_items" rows="12" required>{{ json_encode($lineItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
            </label>
            <div class="admin-form-grid">
                <label>Shipping (€)<input type="number" name="shipping" min="0" step="0.01" value="{{ $quote->shipping }}"></label>
                <label>Discount (€)<input type="number" name="discount" min="0" step="0.01" value="{{ $quote->discount }}"></label>
                <label>Currency<input name="currency_code" maxlength="3" value="{{ $quote->currency_code }}"></label>
                <label>Exchange rate<input type="number" name="exchange_rate" min="0.00000001" step="0.00000001" value="{{ $quote->exchange_rate }}"></label>
            </div>
            <label>Internal notes<textarea name="notes" rows="4">{{ $quote->notes }}</textarea></label>
            <button class="admin-button admin-button--primary" type="submit">Save pricing</button>
        </form>
    </section>

    <section class="admin-action-row" aria-label="Quote actions">
        @if($quote->status === 'submitted')
            <form method="post" action="{{ route('admin.quotes.approve', $quote) }}">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><button class="admin-button admin-button--primary" type="submit">Approve quote</button></form>
            <form method="post" action="{{ route('admin.quotes.reject', $quote) }}">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><input type="hidden" name="note" value="Rejected from the quote queue"><button class="admin-button admin-button--danger" type="submit">Reject quote</button></form>
        @elseif($quote->status === 'approved')
            <form method="post" action="{{ route('admin.quotes.convert', $quote) }}">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><input type="hidden" name="idempotency_key" value="admin-quote-{{ $quote->uuid }}"><button class="admin-button admin-button--primary" type="submit">Convert to {{ str($quote->order_type)->headline() }} order</button></form>
            <form method="post" action="{{ route('admin.quotes.cancel', $quote) }}">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><button class="admin-button admin-button--danger" type="submit">Cancel quote</button></form>
        @endif
    </section>
</div>
@endsection
