<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} - Preview</title>
    <link rel="stylesheet" href="/css/app.css?v=20260905-pages-preview">
</head>
<body class="page-preview-shell">
    <header class="page-preview-top"><strong>Emerald Rozalia</strong><span>Preview mode · {{ ucfirst($page->status) }}</span><a href="{{ route('admin.pages') }}">Back to Pages</a></header>
    <main class="page-preview-content">
        <p class="pages-eyebrow">{{ strtoupper($page->template) }} PAGE</p>
        <h1>{{ $page->title }}</h1>
        @if($page->intro)<p class="page-preview-intro">{{ $page->intro }}</p>@endif
        @if($page->body)<div class="page-preview-body">{!! nl2br(e($page->body)) !!}</div>@endif
        @foreach($page->sections as $section)
            @if($section->visible)
                <section class="page-preview-section page-preview-section--{{ $section->type }}"><h2>{{ $section->label ?: ucfirst($section->type) }}</h2><p>{!! nl2br(e(data_get($section->settings, 'content', 'Section content is ready to be edited in the Page Builder.'))) !!}</p></section>
            @endif
        @endforeach
    </main>
</body>
</html>
