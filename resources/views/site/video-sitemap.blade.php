{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($videos as $video)
    @php($lastmod = $video->updated_at ?: $video->created_at)
    <url><loc>{{ route('videos.watch',$video->uuid) }}</loc>@if($lastmod)<lastmod>{{ \Illuminate\Support\Carbon::parse($lastmod)->toAtomString() }}</lastmod>@endif</url>
@endforeach
</urlset>
