@extends('layouts.admin')

@section('title', $discount ? 'Edit Discount / Coupon' : 'Create Discount / Coupon')

@section('content')
<style>
    .dc-form-page{max-width:980px;font-size:13px}.dc-form-page h1{font-size:25px;margin:0 0 7px}.dc-form-page>p{color:#68766e;margin:0 0 18px;font-size:13px}.dc-form-card{background:#fff;border:1px solid #dce4de;border-radius:7px;padding:21px}.dc-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.dc-field{display:grid;gap:6px}.dc-field.full{grid-column:1/-1}.dc-field label{font-weight:700;color:#26372c}.dc-field input,.dc-field select{width:100%;box-sizing:border-box;border:1px solid #cbd8ce;border-radius:5px;padding:11px;background:#fff;color:#18251d;font-size:13px}.dc-field input:focus,.dc-field select:focus{outline:2px solid #b9dfc5;border-color:#08753b}.dc-help{font-size:13px;color:#68766e}.dc-check{display:flex;gap:8px;align-items:center;padding-top:27px}.dc-check input{width:16px;height:16px}.dc-form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:20px}.dc-btn{display:inline-flex;align-items:center;gap:7px;border:1px solid #cdd9d0;border-radius:5px;background:#fff;color:#26372c;padding:10px 13px;font-size:13px;text-decoration:none;font-weight:700}.dc-btn:hover{border-color:#188047;color:#126c3c}.dc-btn-primary{background:#08753b;border-color:#08753b;color:#fff}.dc-btn-primary:hover{background:#075d30;color:#fff}@media(max-width:650px){.dc-form-grid{grid-template-columns:1fr}.dc-field.full{grid-column:auto}.dc-check{padding-top:0}}
</style>

<div class="dc-form-page">
    <h1>{{ $discount ? 'Edit Discount / Coupon' : 'Create New Discount / Coupon' }}</h1>
    <p>Set the offer, audience and validity rules used during checkout across the six Project 1 order categories.</p>
    <form class="dc-form-card" method="post" action="{{ $discount ? route('admin.discounts-coupons.update', $discount) : route('admin.discounts-coupons.store') }}">
        @csrf
        @if($discount) @method('PATCH') @endif
        <div class="dc-form-grid">
            <div class="dc-field"><label for="code">Coupon code</label><input id="code" name="code" value="{{ old('code', $discount?->code) }}" placeholder="e.g. ER100OFF" required pattern="[A-Za-z0-9_-]+"><span class="dc-help">Letters, numbers, underscores and hyphens only.</span></div>
            <div class="dc-field"><label for="type">Discount type</label><select id="type" name="type" required>@foreach($types as $value=>$label)<option value="{{ $value }}" @selected(old('type', $discount?->type ?: 'percent') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="dc-field"><label for="value">Discount value</label><input id="value" name="value" type="number" min="0" step="0.01" value="{{ old('value', $discount?->value) }}" required><span class="dc-help">For percentage offers, use a value from 0 to 100.</span></div>
            <div class="dc-field"><label for="minimum_order">Minimum order (€)</label><input id="minimum_order" name="minimum_order" type="number" min="0" step="0.01" value="{{ old('minimum_order', $discount?->minimum_order ?: 0) }}"><span class="dc-help">Leave at 0 to apply without a minimum.</span></div>
            <div class="dc-field"><label for="starts_at">Starts at</label><input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $discount?->starts_at?->format('Y-m-d\\TH:i')) }}"></div>
            <div class="dc-field"><label for="ends_at">Ends at</label><input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $discount?->ends_at?->format('Y-m-d\\TH:i')) }}"></div>
            <div class="dc-field"><label for="usage_limit">Usage limit</label><input id="usage_limit" name="usage_limit" type="number" min="1" step="1" value="{{ old('usage_limit', $discount?->usage_limit) }}"><span class="dc-help">Leave blank for unlimited usage.</span></div>
            <div class="dc-check"><input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $discount ? $discount->is_active : true))><label for="is_active">Activate this coupon immediately</label></div>
        </div>
        <div class="dc-form-actions"><a class="dc-btn" href="{{ route('admin.discounts-coupons') }}">Cancel</a><button class="dc-btn dc-btn-primary" type="submit"><x-icon name="check" size="14" /> {{ $discount ? 'Save Coupon' : 'Create Coupon' }}</button></div>
    </form>
</div>
@endsection
