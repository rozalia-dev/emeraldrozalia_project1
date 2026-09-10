@php
    $barItems = collect($items ?? [])->map(function ($item) {
        return [
            'label' => (string) data_get($item, 'label', ''),
            'value' => (float) data_get($item, 'value', data_get($item, 'aggregate', 0)),
        ];
    });
    $max = max(1, (float) ($barItems->max('value') ?: 1));
@endphp
<div class="us-bars">
    @forelse($barItems as $item)
        <div>
            <span>{{ \Illuminate\Support\Str::limit($item['label'], 26) }}</span>
            <i><b style="width:{{ round(($item['value'] / $max) * 100) }}%"></b></i>
            <em>{{ $item['value'] == (int) $item['value'] ? (int) $item['value'] : $item['value'] }}</em>
        </div>
    @empty
        <p class="us-empty">No data yet.</p>
    @endforelse
</div>
