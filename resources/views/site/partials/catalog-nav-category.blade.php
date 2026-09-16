@php($catalogChildren = $catalogCategory->childrenRecursive ?? collect())
@if($catalogChildren->isNotEmpty())
    <details class="site-nav-tree-node" data-depth="{{ $depth }}">
        <summary><b>{{ $catalogCategory->name }}</b><small>{{ $catalogCategory->products_count ?? 0 }}</small><i aria-hidden="true">›</i></summary>
        <div class="site-nav-tree-children">
            <a class="site-nav-tree-view" href="{{ route('category', ['category' => $catalogCategory->slug]) }}"><span>View all {{ $catalogCategory->name }}</span><small>{{ $catalogCategory->products_count ?? 0 }}</small></a>
            @foreach($catalogChildren as $catalogChild)
                @include('site.partials.catalog-nav-category', ['catalogCategory' => $catalogChild, 'depth' => $depth + 1])
            @endforeach
        </div>
    </details>
@else
    <a class="site-nav-tree-leaf" style="--catalog-depth:{{ $depth }}" href="{{ route('category', ['category' => $catalogCategory->slug]) }}"><span>{{ $catalogCategory->name }}</span><small>{{ $catalogCategory->products_count ?? 0 }}</small></a>
@endif
