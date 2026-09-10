@php
    $hasChildren = $category->childrenRecursive->isNotEmpty();
    $parentUuid = $category->parent_id ? ($parentUuid ?? '') : '';
    $rowSelected = $selected && $selected->is($category);
    $indent = max(0, $level) * 22;
@endphp
<tr class="cat-tree-row {{ $level > 0 && ! $expanded ? 'cat-row-collapsed' : '' }} {{ $rowSelected ? 'is-selected' : '' }}"
    data-category-row
    data-uuid="{{ $category->public_uuid }}"
    data-parent="{{ $parentUuid }}"
    data-depth="{{ $level }}"
    draggable="true">
    <td class="cat-check-cell"><input type="checkbox" name="categories[]" value="{{ $category->public_uuid }}" form="category-bulk-form" aria-label="Select {{ $category->name }}"></td>
    <td>
        <div class="cat-name-cell" style="--cat-indent: {{ $indent }}px">
            @if($hasChildren)
                <button type="button" class="cat-tree-toggle" data-tree-toggle="{{ $category->public_uuid }}" aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-label="Toggle {{ $category->name }} sub-categories">
                    <span>›</span>
                </button>
            @else
                <span class="cat-tree-spacer"></span>
            @endif
            <span class="cat-folder"><x-icon name="package" size="15" /></span>
            <a class="cat-category-link" href="{{ route('admin.categories.index', array_merge(request()->except('page','selected'), ['selected' => $category->public_uuid])) }}">
                @if($level === 0)<b>{{ $category->sort_order ?: $loopIndex ?? '' }}.</b>@endif {{ $category->name }}
            </a>
        </div>
    </td>
    <td>{{ number_format($category->products_count ?? 0) }}</td>
    <td><span class="cat-status cat-status--{{ $category->status }}"><i></i>{{ str($category->status)->headline() }}</span></td>
    <td><span class="cat-visibility {{ $category->is_visible ? 'is-visible' : 'is-hidden' }}">{{ $category->is_visible ? 'Visible' : 'Hidden' }}</span></td>
    <td><span class="cat-sort-order">{{ $category->sort_order }}</span></td>
    <td class="cat-actions-cell">
        <details class="cat-row-menu">
            <summary aria-label="Actions for {{ $category->name }}"><x-icon name="dots" size="17" /></summary>
            <div class="cat-row-menu-popover">
                <button type="button" class="js-edit-category"
                    data-id="{{ $category->id }}"
                    data-name="{{ $category->name }}"
                    data-slug="{{ $category->slug }}"
                    data-parent-id="{{ $category->parent_id }}"
                    data-status="{{ $category->status }}"
                    data-visible="{{ $category->is_visible ? '1' : '0' }}"
                    data-sort-order="{{ $category->sort_order }}"
                    data-description="{{ $category->description }}"
                    data-meta-title="{{ $category->meta_title }}"
                    data-meta-description="{{ $category->meta_description }}"
                    data-action="{{ route('admin.categories.update', $category) }}"><x-icon name="pencil" size="14" /> Edit</button>
                <button type="button" class="js-add-subcategory" data-parent-id="{{ $category->id }}" data-parent-name="{{ $category->name }}"><x-icon name="plus" size="14" /> Add Sub-Category</button>
                <form method="POST" action="{{ route('admin.categories.visibility', $category) }}">@csrf<button type="submit"><x-icon name="eye" size="14" /> {{ $category->is_visible ? 'Hide from Website' : 'Show on Website' }}</button></form>
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete {{ addslashes($category->name) }}? This is only allowed when it has no products or sub-categories.')">@csrf @method('DELETE')<button type="submit" class="danger"><x-icon name="trash" size="14" /> Delete</button></form>
            </div>
        </details>
    </td>
</tr>
@foreach($category->childrenRecursive as $child)
    @include('admin.categories._row', [
        'category' => $child,
        'level' => $level + 1,
        'expanded' => $expanded,
        'selected' => $selected,
        'parentUuid' => $category->public_uuid,
        'loopIndex' => null,
    ])
@endforeach
