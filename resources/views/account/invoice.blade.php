<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Invoice {{ $order->number }} · Emerald Rozalia</title>
    <style>
        :root{font-family:Inter,Arial,sans-serif;color:#14251a;background:#f5f6f2}
        body{max-width:920px;margin:0 auto;padding:32px;background:#fff;min-height:100vh}
        header{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;border-bottom:3px solid #0b3a27;padding-bottom:20px}
        header img{width:230px}.brand-copy{text-align:right;font-size:12px;color:#536158}
        h1,h2{font-family:Georgia,serif;color:#0b3a27}h1{font-size:34px;margin:30px 0 8px}
        h2{font-size:20px;margin:28px 0 12px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:24px 0}
        .meta section{border:1px solid #dce4dc;padding:14px}.meta strong,.meta span{display:block}.meta span{font-size:11px;color:#627067;margin-top:5px;line-height:1.5}
        table{width:100%;border-collapse:collapse;margin-top:10px}th,td{padding:11px 9px;border-bottom:1px solid #e2e7e2;text-align:left;font-size:12px}th{background:#f1f4ee;color:#36503c;font-size:10px;text-transform:uppercase;letter-spacing:.06em}.right{text-align:right}
        .totals{max-width:330px;margin:20px 0 0 auto}.totals p{display:flex;justify-content:space-between;margin:7px 0;font-size:12px}.totals p:last-child{padding-top:11px;border-top:2px solid #0b3a27;font-size:17px;font-weight:700}
        .invoice-actions{display:flex;gap:10px;margin-top:30px}.invoice-actions button,.invoice-actions a{padding:11px 16px;border:1px solid #0b3a27;background:#0b3a27;color:#fff;text-decoration:none;cursor:pointer;font-size:11px}.invoice-actions a{background:#fff;color:#0b3a27}
        footer{margin-top:38px;padding-top:15px;border-top:1px solid #dce4dc;color:#66746a;font-size:10px}
        .invoice-logo-image{display:block;width:230px;height:65px;object-fit:cover;object-position:center 65%}
        @media print{body{padding:0} .invoice-actions{display:none}}
        @media(max-width:600px){body{padding:18px}.meta{grid-template-columns:1fr}header{flex-direction:column}.brand-copy{text-align:left}.table-wrap{overflow:auto}table{min-width:600px}}
    </style>
</head>
<body>
    <header>
        <img class="invoice-logo-image" src="{{asset('assets/logo/logo_two_line.png')}}" alt="Emerald Rozalia Limited">
        <div class="brand-copy"><strong>ORDER INVOICE</strong><br>Order {{ $order->number }}<br>{{ $order->created_at?->format('d M Y') }}</div>
    </header>

    <h1>Invoice {{ $order->number }}</h1>
    <p>Status: <strong>{{ str($order->status)->headline() }}</strong> · Payment: <strong>{{ str($order->payment_status)->headline() }}</strong></p>

    <div class="meta">
        <section><strong>Customer</strong><span>{{ $order->user?->name }}<br>{{ $order->email }}</span></section>
        <section><strong>Delivery address</strong><span>{{ data_get($order->shipping_address, 'name') }}<br>{{ data_get($order->shipping_address, 'line1') }}<br>{{ data_get($order->shipping_address, 'city') }} {{ data_get($order->shipping_address, 'postcode') }}<br>{{ data_get($order->shipping_address, 'country') }}</span></section>
    </div>

    <h2>Items</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Unit price</th><th class="right">Total</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr><td>{{ $item->name }}</td><td>{{ $item->sku }}</td><td>{{ $item->quantity }}</td><td>€{{ number_format((float) $item->unit_price, 2) }}</td><td class="right">€{{ number_format((float) $item->total, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <p><span>Subtotal</span><strong>€{{ number_format((float) $order->subtotal, 2) }}</strong></p>
        <p><span>Shipping</span><strong>€{{ number_format((float) $order->shipping, 2) }}</strong></p>
        <p><span>Discount</span><strong>−€{{ number_format((float) $order->discount, 2) }}</strong></p>
        <p><span>{{ $order->currency_code ?: $order->currency }} total</span><strong>€{{ number_format((float) $order->total, 2) }}</strong></p>
    </div>

    <h2>Payment ledger</h2>
    <table>
        <thead><tr><th>Recorded</th><th>Provider / method</th><th>Status</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse($order->payments as $payment)
                <tr><td>{{ $payment->created_at?->format('d M Y H:i') }}</td><td>{{ str($payment->provider)->headline() }}</td><td>{{ str($payment->status)->headline() }}</td><td class="right">€{{ number_format((float) $payment->amount, 2) }}</td></tr>
            @empty
                <tr><td colspan="4">No payment ledger entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="invoice-actions"><button type="button" onclick="window.print()">Print / Save PDF</button>@if(isset($adminContext))<a href="{{ route('admin.order-master.show', [$adminContext['type'], $order]) }}">Back to order</a>@else<a href="{{ route('account.section', 'orders') }}">Back to orders</a>@endif</div>
    <footer>Customer document · {{ $order->currency_code ?: $order->currency }} · Exchange rate {{ $order->exchange_rate ?: '1.00000000' }}</footer>
</body>
</html>
