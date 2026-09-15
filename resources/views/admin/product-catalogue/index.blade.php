@extends('layouts.admin')
@section('title','Product Catalogue')

@push('styles')
<style>
.catalogue-admin{max-width:1180px;margin:0 auto;padding:28px}.catalogue-admin-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.catalogue-admin-head h1{margin:0 0 6px;font-size:28px}.catalogue-admin-head p{margin:0;color:#647168}.catalogue-status{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:8px 12px;font-weight:800;font-size:12px}.catalogue-status.live{background:#e7f7ef;color:#16784a}.catalogue-status.draft{background:#f3f5f1;color:#647168}.catalogue-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:22px}.catalogue-card{background:#fff;border:1px solid #d8dfda;border-radius:10px;padding:22px;box-shadow:0 8px 24px rgba(8,42,25,.05)}.catalogue-card h2{margin:0 0 18px;font-size:18px}.catalogue-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.catalogue-field{display:flex;flex-direction:column;gap:7px}.catalogue-field.full{grid-column:1/-1}.catalogue-field label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.catalogue-field input[type=text],.catalogue-field textarea,.catalogue-field input[type=file]{width:100%;box-sizing:border-box;border:1px solid #cfd8d1;border-radius:7px;padding:11px 12px;background:#fff}.catalogue-field textarea{min-height:110px;resize:vertical}.catalogue-file-note{font-size:12px;color:#647168;margin-top:5px}.catalogue-publish{display:flex;align-items:flex-start;gap:10px;padding:14px;border:1px solid #d8dfda;border-radius:8px;background:#f7f9f7}.catalogue-publish input{margin-top:3px}.catalogue-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}.catalogue-button{border:0;border-radius:7px;padding:11px 16px;background:#075b2f;color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}.catalogue-button.secondary{background:#fff;color:#15221b;border:1px solid #cfd8d1}.catalogue-stat{display:flex;justify-content:space-between;padding:13px 0;border-bottom:1px solid #edf0ed}.catalogue-stat:last-child{border-bottom:0}.catalogue-stat span{color:#647168}.catalogue-stat strong{text-align:right}.catalogue-preview{margin-top:18px;aspect-ratio:4/5;border-radius:8px;overflow:hidden;background:linear-gradient(145deg,#075b2f,#052617);display:flex;align-items:center;justify-content:center;color:#fff;text-align:center;padding:20px}.catalogue-preview img{width:100%;height:100%;object-fit:cover}.catalogue-errors{margin-bottom:18px;padding:12px 14px;background:#fff1f0;border:1px solid #ffc9c4;border-radius:8px;color:#8c1d18}.catalogue-success{margin-bottom:18px;padding:12px 14px;background:#ebf8f0;border:1px solid #bce7ca;border-radius:8px;color:#14653e}@media(max-width:850px){.catalogue-grid{grid-template-columns:1fr}.catalogue-form-grid{grid-template-columns:1fr}.catalogue-field.full{grid-column:auto}.catalogue-admin-head{flex-direction:column}}
</style>
@endpush

@section('content')
<div class="catalogue-admin">
    <div class="catalogue-admin-head">
        <div><h1>Product Catalogue</h1><p>Upload, publish and replace the customer-downloadable Emerald Rozalia catalogue.</p></div>
        <span class="catalogue-status {{ $catalogue->is_published ? 'live' : 'draft' }}">{{ $catalogue->is_published ? '● LIVE' : '○ NOT PUBLISHED' }}</span>
    </div>

    @if(session('success'))<div class="catalogue-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="catalogue-errors">{{ implode(' ', $errors->all()) }}</div>@endif

    <div class="catalogue-grid">
        <section class="catalogue-card">
            <h2>Catalogue settings</h2>
            <form method="post" action="{{ route('admin.product-catalogue.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="catalogue-form-grid">
                    <div class="catalogue-field full">
                        <label for="catalogue-title">Catalogue title</label>
                        <input id="catalogue-title" type="text" name="title" value="{{ old('title',$catalogue->title ?: 'Emerald Rozalia Product Catalogue') }}" required maxlength="160">
                    </div>
                    <div class="catalogue-field">
                        <label for="catalogue-version">Version / edition</label>
                        <input id="catalogue-version" type="text" name="version" value="{{ old('version',$catalogue->version) }}" placeholder="2026 / Autumn 2026" maxlength="80">
                    </div>
                    <div class="catalogue-field">
                        <label for="catalogue-pdf">Catalogue PDF</label>
                        <input id="catalogue-pdf" type="file" name="catalogue_pdf" accept="application/pdf,.pdf">
                        <span class="catalogue-file-note">PDF only · max 50 MB. Uploading a new file replaces the previous file.</span>
                    </div>
                    <div class="catalogue-field full">
                        <label for="catalogue-cover">Cover image</label>
                        <input id="catalogue-cover" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                        <span class="catalogue-file-note">Optional JPG, PNG or WebP · max 8 MB.</span>
                    </div>
                    <div class="catalogue-field full">
                        <label for="catalogue-description">Description</label>
                        <textarea id="catalogue-description" name="description" maxlength="2000" placeholder="Short public description of this catalogue.">{{ old('description',$catalogue->description) }}</textarea>
                    </div>
                    <label class="catalogue-publish catalogue-field full">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published',$catalogue->is_published))>
                        <span><strong>Publish catalogue for customers</strong><br><small>When disabled, the public catalogue page and download return 404. A PDF is required before publishing.</small></span>
                    </label>
                </div>
                <div class="catalogue-actions">
                    <button class="catalogue-button" type="submit"><x-icon name="save" size="15" /> SAVE CATALOGUE</button>
                    @if($catalogue->exists && $catalogue->pdf_path)
                        <a class="catalogue-button secondary" href="{{ route('admin.product-catalogue.download') }}"><x-icon name="download" size="15" /> DOWNLOAD CURRENT PDF</a>
                    @endif
                    @if($catalogue->is_published)
                        <a class="catalogue-button secondary" href="{{ route('catalogue.show') }}" target="_blank" rel="noopener"><x-icon name="globe" size="15" /> VIEW PUBLIC PAGE</a>
                    @endif
                </div>
            </form>
        </section>

        <aside class="catalogue-card">
            <h2>Current catalogue</h2>
            <div class="catalogue-stat"><span>File</span><strong>{{ $catalogue->pdf_original_name ?: 'Not uploaded' }}</strong></div>
            <div class="catalogue-stat"><span>Version</span><strong>{{ $catalogue->version ?: '—' }}</strong></div>
            <div class="catalogue-stat"><span>PDF size</span><strong>{{ $catalogue->pdf_size ? number_format($catalogue->pdf_size / 1048576,1).' MB' : '—' }}</strong></div>
            <div class="catalogue-stat"><span>Public downloads</span><strong>{{ number_format((int) $catalogue->download_count) }}</strong></div>
            <div class="catalogue-stat"><span>Published</span><strong>{{ $catalogue->published_at?->format('d M Y H:i') ?: 'No' }}</strong></div>
            <div class="catalogue-preview">
                @if($catalogue->is_published && $catalogue->cover_path)
                    <img src="{{ route('catalogue.cover') }}" alt="Catalogue cover preview">
                @else
                    <div><strong>EMERALD ROZALIA LIMITED</strong><br><small>Product Catalogue</small></div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
