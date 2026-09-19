<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $catalogue->title ?: 'Emerald Rozalia Product Catalogue' }}</title>
    <style>
        @page{size:A4;margin:12mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#15221b;margin:0;background:#fff}.catalogue-print{max-width:190mm;margin:0 auto}.catalogue-print-head{border-bottom:2px solid #075b2f;padding:0 0 12px;margin-bottom:16px;display:flex;justify-content:space-between;gap:20px;align-items:flex-end}.catalogue-print-brand{padding:8px 10px;background:#06140d;border-radius:5px}.catalogue-print-brand img{display:block;width:60mm;max-width:100%;max-height:18mm;object-fit:contain;object-position:left center;margin-bottom:4px}.catalogue-print-brand span{color:#eef5ef;font-size:10px;letter-spacing:.09em}.catalogue-print-meta{text-align:right;font-size:10px;line-height:1.5;color:#59675f}.catalogue-print-intro{margin-bottom:18px}.catalogue-print-intro h1{font-size:30px;line-height:1.08;margin:0 0 8px}.catalogue-print-intro p{font-size:11px;line-height:1.55;color:#59675f;margin:0;max-width:150mm}.catalogue-print-category{margin:18px 0 0;break-inside:avoid-page}.catalogue-print-category h2{font-size:16px;margin:0 0 9px;padding-bottom:6px;border-bottom:1px solid #d8dfda}.catalogue-print-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.catalogue-print-card{border:1px solid #d8dfda;border-radius:6px;overflow:hidden;break-inside:avoid;background:#fff}.catalogue-print-media{aspect-ratio:1/1;background:#f3f5f1;display:flex;align-items:center;justify-content:center;overflow:hidden}.catalogue-print-media img{width:100%;height:100%;object-fit:cover}.catalogue-print-media span{font-size:9px;color:#738077;padding:10px;text-align:center}.catalogue-print-info{padding:9px}.catalogue-print-info .cat{display:block;color:#075b2f;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}.catalogue-print-info h3{font-size:11px;line-height:1.3;margin:0 0 4px}.catalogue-print-info p{font-size:8.5px;line-height:1.4;color:#59675f;margin:0 0 6px}.catalogue-print-identifiers{display:grid;gap:3px;margin:0 0 6px;color:#59675f;font-size:6.5px;line-height:1.25}.catalogue-print-identifiers span{font-weight:700}.catalogue-print-identifiers code{overflow-wrap:anywhere;color:#15221b;font:6px/1.25 ui-monospace,SFMono-Regular,Menlo,monospace}.catalogue-print-coming-soon{border-style:dashed;background:#f5f8f3}.catalogue-print-coming-soon .catalogue-print-media span{font-size:10px;font-weight:700;letter-spacing:.08em;color:#075b2f}.catalogue-print-foot{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:8px;color:#68766d}.catalogue-print-foot strong{font-size:10px;color:#15221b}.catalogue-print-empty{border:1px dashed #cbd6ce;padding:24px;text-align:center;color:#68766d}.catalogue-print-footer{margin-top:18px;padding-top:8px;border-top:1px solid #d8dfda;font-size:8px;color:#68766d;display:flex;justify-content:space-between;gap:10px}.screen-actions{position:sticky;top:0;z-index:2;display:flex;justify-content:flex-end;gap:8px;padding:10px;background:#fff;border-bottom:1px solid #e4e8e5;margin-bottom:14px}.screen-actions button,.screen-actions a{border:1px solid #cfd8d1;border-radius:5px;padding:8px 11px;background:#fff;color:#15221b;text-decoration:none;font:700 12px Arial;cursor:pointer}.screen-actions button{background:#075b2f;color:#fff;border-color:#075b2f}@media print{.screen-actions{display:none}.catalogue-print{max-width:none}.catalogue-print-category{break-inside:auto}}
    </style>
</head>
<body>
<div class="screen-actions"><a href="{{ route('catalogue.show') }}">Back to catalogue</a><button type="button" onclick="window.print()">Print / Save PDF</button></div>
<main class="catalogue-print">
    <header class="catalogue-print-head">
        <div class="catalogue-print-brand"><img src="{{ asset('assets/logo/logo_one_line.png') }}" alt="Emerald Rozalia"><span>IRISH MANUFACTURER · LIMERICK, IRELAND</span></div>
        <div class="catalogue-print-meta">Generated from current published products<br>{{ $generatedAt->format('d M Y H:i') }} · {{ $products->count() }} products</div>
    </header>
    <section class="catalogue-print-intro">
        <h1>{{ $catalogue->title ?: 'Emerald Rozalia Product Catalogue' }}</h1>
        <p>{{ $catalogue->description ?: 'Current Emerald Rozalia hats and caps catalogue generated directly from products published on the website.' }}</p>
    </section>

    @if($categories->isNotEmpty())
        @foreach($categories as $category)
            @php($categoryName = $category['name'])
            @php($categoryProducts = $category['products'])
            <section class="catalogue-print-category">
                <h2>{{ $categoryName }}</h2>
                <div class="catalogue-print-grid">
                    @foreach($categoryProducts as $product)
                        @php($image = $productMedia[$product->id] ?? null)
                        <article class="catalogue-print-card">
                            <div class="catalogue-print-media">
                                @if($image)<img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">@else<span>Product image not configured</span>@endif
                            </div>
                            <div class="catalogue-print-info">
                                <span class="cat">{{ $categoryName }}</span>
                                <h3>{{ $product->name }}</h3>
                                @if($product->description)<p>{{ \Illuminate\Support\Str::limit(strip_tags((string)$product->description),120) }}</p>@endif
                                <div class="catalogue-print-identifiers">
                                    <div><span>Product UUID</span> <code>{{ $product->public_uuid ?: 'Not assigned' }}</code></div>
                                    <div><span>Barcode</span> Not assigned</div>
                                </div>
                                <div class="catalogue-print-foot"><span>SKU {{ $product->sku }}</span><strong>€{{ number_format((float)$product->price,2) }}</strong></div>
                            </div>
                        </article>
                    @endforeach
                    @for($slot = $categoryProducts->count(); $slot < 4; $slot++)
                        <article class="catalogue-print-card catalogue-print-coming-soon">
                            <div class="catalogue-print-media"><span>MORE STYLES<br>COMING SOON</span></div>
                            <div class="catalogue-print-info">
                                <span class="cat">{{ $categoryName }}</span>
                                <h3>More styles coming soon</h3>
                                <p>New products will appear here when available.</p>
                            </div>
                        </article>
                    @endfor
                </div>
            </section>
        @endforeach
    @else
        <div class="catalogue-print-empty">No published products are currently available.</div>
    @endif

    <footer class="catalogue-print-footer"><span>Emerald Rozalia Limited · Product Catalogue</span><span>Current product data · generated {{ $generatedAt->format('d M Y') }}</span></footer>
</main>
@if(request()->boolean('autoprint'))
<script>window.addEventListener('load',function(){window.setTimeout(function(){window.print();},350);});</script>
@endif
</body>
</html>
