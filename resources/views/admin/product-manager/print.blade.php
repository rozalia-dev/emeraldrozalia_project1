<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Product Manager Print</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;margin:24px}
        h1{margin:0 0 6px;font-size:22px}.meta{color:#555;font-size:12px;margin-bottom:18px}
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{border:1px solid #ccc;padding:6px;text-align:left}
        th{background:#f2f2f2}
        @media print{button{display:none}body{margin:0}}
    </style>
</head>
<body>
    <button type="button" onclick="window.print()">Print</button>
    <h1>Product Manager</h1>
    <div class="meta">Generated {{ $generatedAt->format('Y-m-d H:i') }} · Up to 1,000 matching products shown for print.</div>
    <table>
        <thead>
            <tr><th>ID</th><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td>{{ $product->id }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->category?->name ?: 'Uncategorised' }}</td>
                <td>{{ number_format((float)$product->price,2) }}</td>
                <td>{{ number_format((int)$product->stock) }}</td>
                <td>{{ $product->isPubliclyPublished() ? 'Published' : ucfirst((string)$product->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No matching products.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
