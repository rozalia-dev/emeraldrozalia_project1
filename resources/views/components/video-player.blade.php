@php($detail = $video->details())
<div class="er-video-player">
    @if($detail['playback'])
        <video controls playsinline preload="metadata" style="width:100%;max-height:520px;background:#06100b;border-radius:8px" @if($detail['poster']) poster="{{ $detail['poster'] }}" @endif
            data-er-video data-track-url="{{ route('videos.record',$video->uuid) }}" data-csrf="{{ csrf_token() }}"
            @if(!$detail['allow_download']) controlslist="nodownload" @endif>
            <source src="{{ $detail['playback'] }}">
            @if($detail['captions'])<track kind="captions" src="{{ $detail['captions'] }}" srclang="{{ $detail['caption_language'] }}" label="{{ strtoupper($detail['caption_language']) }}" default>@endif
            Your browser cannot play this format. Try an MP4 video.
        </video>
    @elseif($detail['embed'])
        <iframe src="{{ $detail['embed'] }}" title="{{ $video->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; picture-in-picture" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0;border-radius:8px"></iframe>
    @else
        <p>This video is unavailable. Please contact the administrator.</p>
    @endif
    @if($detail['allow_download'] && $detail['playback'])
        <a href="{{ $detail['playback'] }}?download=1">Download video</a>
    @endif
</div>
@once
<script src="/js/video-playback.js?v=20260908" defer></script>
@endonce
