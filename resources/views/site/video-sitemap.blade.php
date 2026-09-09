{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($videos as $video)
    <url><loc>{{ route('videos.watch',$video->uuid) }}</loc><lastmod>{{ $video->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
