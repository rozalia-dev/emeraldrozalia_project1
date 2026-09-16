@extends('layouts.admin')

@section('title', 'Quote '.$quote->uuid)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-quotes.css?v=20260916-3') }}">
@endpush

@section('content')
@php
    $money = static fn ($value): string => '€'.number_format((float) $value, 2);
    $inquiry = $quote->inquiry;
    $lineItems = is_array($quote->line_items) ? $quote->line_items : [];
    $editorLines = old('line_items', $lineItems);
    if (!is_array($editorLines) || count($editorLines) === 0) {
        $editorLines = [['product_id' => '', 'variant_id' => '', 'quantity' => 1, 'unit_price' => '']];
    }
    $isSubmitted = $quote->status === 'submitted';
    $isApproved = $quote->status === 'approved';
    $isConverted = $quote->status === 'converted' || (bool) $quote->order;
@endphp
<div class="quotes-page quotes-detail-page" data-sales-quote-show>
    <nav class="quotes-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Project 1 Control Panel</a><x-icon name="chevron-right" size="11" />
        <a href="{{ route('admin.quotes.index') }}">Sales Quotes</a><x-icon name="chevron-right" size="11" />
        <strong>{{ str($quote->order_type)->headline() }} Quote</strong>
    </nav>

    <header class="quotes-hero quotes-detail-hero">
        <div class="quotes-hero-copy">
            <span class="quotes-kicker">{{ strtoupper(str($quote->order_type)->headline()) }} PRE-ORDER</span>
            <h1>Quote {{ str($quote->uuid)->limit(18, '…') }}</h1>
            <p>Review the public request, set commercial terms, approve the quote, then convert it into the {{ str($quote->order_type)->headline() }} Order Master.</p>
        </div>
        <div class="quotes-hero-actions">
            <span class="quotes-status quotes-status--{{ str($quote->status)->slug() }}">{{ str($quote->status)->replace('_', ' ')->headline() }}</span>
            <a class="quotes-btn quotes-btn--soft" href="{{ route('admin.quotes.index') }}"><x-icon name="chevron-left" size="13" /> Back to queue</a>
        </div>
    </header>

    @if(session('success'))
        <div class="quotes-alert quotes-alert--success"><x-icon name="check" size="16" /><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
        <div class="quotes-alert quotes-alert--error"><x-icon name="alert" size="16" /><div><strong>Please correct the quote before continuing.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <section class="quotes-flow quotes-flow--detail" aria-label="Quote conversion workflow">
        <ol>
            <li class="{{ $isSubmitted ? 'is-current' : (!$isSubmitted ? 'is-complete' : '') }}"><span>1</span><div><b>Review &amp; Price</b><small>{{ count($lineItems) }} priced line item{{ count($lineItems) === 1 ? '' : 's' }}</small></div></li>
            <li class="{{ $isApproved ? 'is-current' : ($isConverted ? 'is-complete' : '') }}"><span>2</span><div><b>Approve Quote</b><small>{{ $quote->approved_at ? 'Approved '.$quote->approved_at->format('d M Y H:i') : 'Awaiting commercial approval' }}</small></div></li>
            <li class="{{ $isConverted ? 'is-complete is-current' : '' }}"><span>3</span><div><b>Convert to Order</b><small>{{ $quote->order ? 'Order '.$quote->order->number : 'Creates the real Order Master record' }}</small></div></li>
        </ol>
    </section>

    <div class="quotes-detail-grid">
        <main class="quotes-detail-main">
            <section class="quotes-card">
                <header class="quotes-card-head"><div><h2>Requester &amp; public request</h2><p>The source enquiry remains linked to its Communication Center thread.</p></div><span class="quotes-master quotes-master--{{ str($quote->order_type)->slug() }}">{{ str($quote->order_type)->headline() }} Orders</span></header>
                <dl class="quotes-detail-list">
                    <div><dt>Requester</dt><dd>{{ $inquiry?->name ?: 'Not supplied' }}</dd></div>
                    <div><dt>Company</dt><dd>{{ $inquiry?->company ?: 'Not supplied' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $inquiry?->email ?: 'Not supplied' }}</dd></div>
                    <div><dt>Enquiry type</dt><dd>{{ $inquiry?->type ?: 'Not supplied' }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ optional($quote->submitted_at)->format('d M Y H:i') ?: 'Not recorded' }}</dd></div>
                    <div><dt>Conversation</dt><dd>{{ $quote->conversation?->uuid ?: 'Not linked' }}</dd></div>
                </dl>
                @if($inquiry?->message)<div class="quotes-request-note"><strong>Customer requirements</strong><p>{{ $inquiry->message }}</p></div>@endif
            </section>

            <section class="quotes-card">
                <header class="quotes-card-head"><div><h2>Pricing &amp; line items</h2><p>Select products from the live catalogue, set quantity and negotiated unit price, then save before approval.</p></div><span class="quotes-version">Version {{ $quote->version }}</span></header>
                <form class="quotes-pricing-form" method="post" action="{{ route('admin.quotes.update', $quote) }}" data-quote-pricing-form>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="expected_version" value="{{ $quote->version }}">

                    <div class="quotes-line-editor">
                        <div class="quotes-line-toolbar"><div><strong>Quote products</strong><span>Use the current product catalogue; variants are optional.</span></div><button class="quotes-btn quotes-btn--soft quotes-btn--sm" type="button" data-add-quote-line><x-icon name="plus" size="12" /> Add product</button></div>
                        <div class="quotes-line-head" aria-hidden="true"><span>Product</span><span>Variant</span><span>Qty</span><span>Unit price</span><span></span></div>
                        <div data-quote-lines>
                            @foreach($editorLines as $index => $line)
                                <div class="quotes-line-row" data-quote-line>
                                    <label><span>Product</span><select name="line_items[{{ $index }}][product_id]" data-product-select required><option value="">Select product…</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->price }}" @selected((int) data_get($line, 'product_id') === (int) $product->id)>{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }} · €{{ number_format((float)$product->price, 2) }}</option>@endforeach</select></label>
                                    <label><span>Variant</span><select name="line_items[{{ $index }}][variant_id]" data-variant-select><option value="">Base product</option>@foreach($products as $product)@foreach($product->variants as $variant)<option value="{{ $variant->id }}" data-product="{{ $product->id }}" data-price="{{ $variant->price ?? $product->price }}" @selected((int) data_get($line, 'variant_id') === (int) $variant->id)>{{ $product->name }} · {{ $variant->sku ?: 'Variant '.$variant->id }}</option>@endforeach @endforeach</select></label>
                                    <label><span>Qty</span><input type="number" name="line_items[{{ $index }}][quantity]" min="1" max="100000" value="{{ data_get($line, 'quantity', 1) }}" required></label>
                                    <label><span>Unit price (€)</span><input type="number" name="line_items[{{ $index }}][unit_price]" min="0" max="999999999.99" step="0.01" value="{{ data_get($line, 'unit_price') }}" data-unit-price></label>
                                    <button class="quotes-line-remove" type="button" data-remove-quote-line title="Remove product" aria-label="Remove product"><x-icon name="trash" size="14" /></button>
                                </div>
                            @endforeach
                        </div>
                        @if($products->isEmpty())<div class="quotes-catalog-warning"><x-icon name="alert" size="14" /><span>No published products are available for this company. Publish a product before pricing this quote.</span></div>@endif
                    </div>

                    <div class="quotes-form-grid">
                        <label><span>Shipping (€)</span><input type="number" name="shipping" min="0" step="0.01" value="{{ old('shipping', $quote->shipping) }}"></label>
                        <label><span>Discount (€)</span><input type="number" name="discount" min="0" step="0.01" value="{{ old('discount', $quote->discount) }}"></label>
                        <label><span>Currency</span><input name="currency_code" maxlength="3" value="{{ old('currency_code', $quote->currency_code) }}"></label>
                        <label><span>Exchange rate</span><input type="number" name="exchange_rate" min="0.00000001" step="0.00000001" value="{{ old('exchange_rate', $quote->exchange_rate) }}"></label>
                    </div>
                    <label><span>Internal notes</span><textarea name="notes" rows="4">{{ old('notes', $quote->notes) }}</textarea></label>
                    <div class="quotes-form-actions"><button class="quotes-btn quotes-btn--primary" type="submit"><x-icon name="check" size="13" /> Save pricing &amp; terms</button><span>Saving pricing recalculates the quote total and increments its audit version.</span></div>
                </form>
            </section>
        </main>

        <aside class="quotes-detail-side">
            <section class="quotes-card quotes-summary-card">
                <header class="quotes-card-head"><div><h2>Commercial summary</h2><p>Current conversion value.</p></div></header>
                <dl class="quotes-money-list">
                    <div><dt>Subtotal</dt><dd>{{ $money($quote->subtotal) }}</dd></div>
                    <div><dt>Shipping</dt><dd>{{ $money($quote->shipping) }}</dd></div>
                    <div><dt>Discount</dt><dd>-{{ $money($quote->discount) }}</dd></div>
                    <div class="is-total"><dt>Total</dt><dd>{{ $money($quote->total) }} <small>{{ $quote->currency_code }}</small></dd></div>
                </dl>
            </section>

            <section class="quotes-card quotes-action-card">
                <header class="quotes-card-head"><div><h2>Admin action</h2><p>The available action follows the quote lifecycle.</p></div></header>

                @if($isSubmitted)
                    <div class="quotes-action-explainer"><span>STEP 2</span><strong>Approve after pricing</strong><p>The quote must contain at least one priced line item before approval.</p></div>
                    <form method="post" action="{{ route('admin.quotes.approve', $quote) }}">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><button class="quotes-btn quotes-btn--primary quotes-btn--block" type="submit"><x-icon name="check" size="14" /> Approve quote</button></form>
                    <form method="post" action="{{ route('admin.quotes.reject', $quote) }}" onsubmit="return confirm('Reject this quote?')">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><input type="hidden" name="note" value="Rejected from the quote review"><button class="quotes-btn quotes-btn--danger quotes-btn--block" type="submit">Reject quote</button></form>
                @elseif($isApproved)
                    <div class="quotes-action-explainer quotes-action-explainer--ready"><span>STEP 3</span><strong>Ready to convert</strong><p>This creates the real {{ str($quote->order_type)->headline() }} Order Master record and redirects you to it.</p></div>
                    <form method="post" action="{{ route('admin.quotes.convert', $quote) }}" onsubmit="return confirm('Create the {{ str($quote->order_type)->headline() }} Order from this approved quote?')">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $quote->version }}">
                        <input type="hidden" name="idempotency_key" value="admin-quote-{{ $quote->uuid }}">
                        <button class="quotes-btn quotes-btn--primary quotes-btn--block" type="submit"><x-icon name="shopping-bag" size="14" /> Convert to {{ str($quote->order_type)->headline() }} Order</button>
                    </form>
                    <form method="post" action="{{ route('admin.quotes.cancel', $quote) }}" onsubmit="return confirm('Cancel this approved quote?')">@csrf<input type="hidden" name="expected_version" value="{{ $quote->version }}"><button class="quotes-btn quotes-btn--danger quotes-btn--block" type="submit">Cancel quote</button></form>
                @elseif($quote->order)
                    <div class="quotes-action-explainer quotes-action-explainer--ready"><span>COMPLETE</span><strong>Order created</strong><p>The quote is now represented by a real Order Master record.</p></div>
                    <a class="quotes-btn quotes-btn--primary quotes-btn--block" href="{{ route('admin.order-master.show', [$quote->order->order_type, $quote->order]) }}"><x-icon name="shopping-bag" size="14" /> Open Order {{ $quote->order->number }}</a>
                @else
                    <div class="quotes-action-explainer"><span>{{ strtoupper($quote->status) }}</span><strong>No conversion action available</strong><p>This quote remains in the audit trail and cannot be converted in its current state.</p></div>
                @endif
            </section>

            <section class="quotes-card quotes-trace-card">
                <header class="quotes-card-head"><div><h2>Traceability</h2></div></header>
                <dl class="quotes-trace-list">
                    <div><dt>Quote UUID</dt><dd>{{ $quote->uuid }}</dd></div>
                    <div><dt>Correlation ID</dt><dd>{{ $quote->correlation_id ?: 'Not supplied' }}</dd></div>
                    <div><dt>Order type</dt><dd>{{ str($quote->order_type)->headline() }}</dd></div>
                    <div><dt>Converted order</dt><dd>@if($quote->order)<a href="{{ route('admin.order-master.show', [$quote->order->order_type, $quote->order]) }}">{{ $quote->order->number }}</a>@else Not converted @endif</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>

<template id="quote-line-template">
    <div class="quotes-line-row" data-quote-line>
        <label><span>Product</span><select name="line_items[__INDEX__][product_id]" data-product-select required><option value="">Select product…</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->price }}">{{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }} · €{{ number_format((float)$product->price, 2) }}</option>@endforeach</select></label>
        <label><span>Variant</span><select name="line_items[__INDEX__][variant_id]" data-variant-select><option value="">Base product</option>@foreach($products as $product)@foreach($product->variants as $variant)<option value="{{ $variant->id }}" data-product="{{ $product->id }}" data-price="{{ $variant->price ?? $product->price }}">{{ $product->name }} · {{ $variant->sku ?: 'Variant '.$variant->id }}</option>@endforeach @endforeach</select></label>
        <label><span>Qty</span><input type="number" name="line_items[__INDEX__][quantity]" min="1" max="100000" value="1" required></label>
        <label><span>Unit price (€)</span><input type="number" name="line_items[__INDEX__][unit_price]" min="0" max="999999999.99" step="0.01" data-unit-price></label>
        <button class="quotes-line-remove" type="button" data-remove-quote-line title="Remove product" aria-label="Remove product"><x-icon name="trash" size="14" /></button>
    </div>
</template>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-sales-quote-show]');
    if (!root) return;
    const list = root.querySelector('[data-quote-lines]');
    const add = root.querySelector('[data-add-quote-line]');
    const template = document.getElementById('quote-line-template');
    if (!list || !add || !template) return;

    const syncVariants = (row, resetSelection = false) => {
        const product = row.querySelector('[data-product-select]');
        const variant = row.querySelector('[data-variant-select]');
        const price = row.querySelector('[data-unit-price]');
        if (!product || !variant || !price) return;
        const productId = product.value;

        [...variant.options].forEach((option, index) => {
            if (index === 0) return;
            const compatible = option.dataset.product === productId;
            option.hidden = !compatible;
            option.disabled = !compatible;
            if (!compatible && option.selected) option.selected = false;
        });
        if (resetSelection) variant.value = '';
        if (productId && (resetSelection || price.value === '')) {
            price.value = product.selectedOptions[0]?.dataset.price || '';
        }
    };

    const wireRow = (row) => {
        const product = row.querySelector('[data-product-select]');
        const variant = row.querySelector('[data-variant-select]');
        const price = row.querySelector('[data-unit-price]');
        const remove = row.querySelector('[data-remove-quote-line]');
        syncVariants(row, false);
        product?.addEventListener('change', () => syncVariants(row, true));
        variant?.addEventListener('change', () => {
            const selected = variant.selectedOptions[0];
            if (selected?.value && selected.dataset.price) price.value = selected.dataset.price;
        });
        remove?.addEventListener('click', () => {
            const rows = list.querySelectorAll('[data-quote-line]');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('select').forEach((field) => field.selectedIndex = 0);
                row.querySelectorAll('input').forEach((field) => field.value = field.type === 'number' && field.name.includes('[quantity]') ? '1' : '');
                syncVariants(row, true);
            }
        });
    };

    list.querySelectorAll('[data-quote-line]').forEach(wireRow);
    add.addEventListener('click', () => {
        const nextIndex = [...list.querySelectorAll('[data-quote-line]')].reduce((max, row) => {
            const field = row.querySelector('[name^="line_items["]');
            const match = field?.name.match(/line_items\[(\d+)\]/);
            return Math.max(max, match ? Number(match[1]) : -1);
        }, -1) + 1;
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex)).trim();
        const row = holder.firstElementChild;
        list.appendChild(row);
        wireRow(row);
        row.querySelector('[data-product-select]')?.focus();
    });
})();
</script>
@endpush
