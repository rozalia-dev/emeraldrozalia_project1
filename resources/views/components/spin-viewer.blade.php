@props(['spin','preview'=>false])
<div class="sv" data-spin-widget data-config="{{ json_encode($spin->viewerData()) }}" data-preview="{{ $preview || auth()->user()?->is_admin ? '1' : '0' }}" data-token="{{ csrf_token() }}">
    <div class="sv-stage" tabindex="0" role="group" aria-label="{{ $spin->seo['aria'] ?? $spin->title }}">
        <img data-sv-image src="{{ route('spins.frame',[$spin->uuid,0]) }}" alt="{{ $spin->seo['alt'] ?? $spin->title }}" loading="{{ ($spin->settings['lazy_load']??true)?'lazy':'eager' }}" draggable="false">
        <div class="sv-hotspots"></div>
        <span class="sv-frame">1 / {{ count($spin->frames) }}</span>
    </div>
    <div class="sv-controls">
        <button type="button" data-sv-prev aria-label="Rotate left">←</button>
        <button type="button" data-sv-play aria-label="Toggle auto rotation" aria-pressed="false">Play</button>
        <input type="range" data-sv-range min="0" max="{{ count($spin->frames)-1 }}" value="0" aria-label="Rotation frame">
        <button type="button" data-sv-next aria-label="Rotate right">→</button>
        @if($spin->settings['zoom']??true)<button type="button" data-sv-zoom aria-label="Toggle zoom" aria-pressed="false">Zoom</button>@endif
        @if($spin->settings['fullscreen']??true)<button type="button" data-sv-full aria-label="Open fullscreen">⛶</button>@endif
    </div>
    <p class="sv-status" role="status">Drag or swipe to rotate. Use left/right arrow keys.</p>
</div>
