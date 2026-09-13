<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{MediaAsset, MediaAssetVersion};
use App\Rules\MediaDimensions;
use App\Services\{AuditTrail, MediaAssetUsageService, PublicMediaDerivativeService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAssetController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'archived'];

    public function index(Request $request, MediaAssetUsageService $usage)
    {
        $view = ($request->query('view') === 'trash' || $request->route('view') === 'trash') ? 'trash' : 'active';
        $status = in_array($request->string('status')->toString(), self::STATUSES, true)
            ? $request->string('status')->toString()
            : '';
        $query = MediaAsset::query()->visibleToCurrentCompany()->latest('created_at');
        if ($view === 'trash') {
            $query->withTrashed()->whereNotNull('deleted_at');
        }
        if ($status !== '') {
            $query->where('approval_status', $status);
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('alt_text', 'like', '%'.$term.'%'));
        }

        $assets = $query->paginate(24)->withQueryString();
        $assetIds = $assets->getCollection()->modelKeys();
        $usageByAsset = $assets->getCollection()->mapWithKeys(fn (MediaAsset $asset): array => [
            $asset->id => $usage->for($asset),
        ]);
        $versionsByAsset = MediaAssetVersion::query()
            ->whereIn('media_asset_id', $assetIds ?: [0])
            ->latest('version')
            ->get()
            ->groupBy('media_asset_id');

        $visibleAssets = fn () => MediaAsset::query()->visibleToCurrentCompany();
        $stats = [
            'total' => $visibleAssets()->count(),
            'approved' => $visibleAssets()->where('approval_status', 'approved')->where('active', true)->count(),
            'pending' => $visibleAssets()->where('approval_status', 'pending')->count(),
            'attention' => $visibleAssets()->whereIn('approval_status', ['rejected', 'archived'])->count(),
            'trash' => MediaAsset::onlyTrashed()->visibleToCurrentCompany()->count(),
        ];

        return view('admin.media-assets.index', compact(
            'assets', 'status', 'view', 'usageByAsset', 'versionsByAsset', 'stats'
        ) + ['statuses' => self::STATUSES]);
    }

    public function preview(string $asset)
    {
        $asset = $this->findAsset($asset);
        abort_if($asset->trashed(), 404);

        $disk = (string) ($asset->disk ?: 'local');
        abort_unless(in_array($disk, ['local', 'public'], true), 404);
        abort_unless($this->safePath($asset->path), 404);

        $storage = Storage::disk($disk);
        abort_unless($storage->exists($asset->path), 404);

        $mime = $asset->mime_type ?: $storage->mimeType($asset->path);

        return response()->file($storage->path($asset->path), [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.addcslashes(basename($asset->path), '"\\').'"',
        ]);
    }

    public function store(Request $request, PublicMediaDerivativeService $derivatives): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', new MediaDimensions, 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm'],
            'name' => ['nullable', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('site-media', 'local');
        $dimensions = @getimagesize($file->getRealPath());
        $asset = MediaAsset::create([
            'name' => $data['name'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'bytes' => (int) ($file->getSize() ?: 0),
            'width' => (int) ($dimensions[0] ?? 0) ?: null,
            'height' => (int) ($dimensions[1] ?? 0) ?: null,
            'alt_text' => $data['alt_text'] ?? null,
            'approval_status' => 'pending',
            'active' => false,
            'metadata' => [
                'original_name' => $file->getClientOriginalName(),
                'security_scan' => 'queued_for_review',
            ],
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->recordCurrentVersion($asset, 1, $derivatives->generate($asset, 1));
        AuditTrail::record('site-media.created', $asset, null, $asset->fresh()->toArray());

        return back()->with('success', 'Media uploaded and queued for approval.');
    }

    public function update(Request $request, MediaAsset $asset, PublicMediaDerivativeService $derivatives): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'focal_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'focal_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crop_mode' => ['nullable', 'in:cover,contain,none'],
            'crop_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crop_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'crop_width' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'crop_height' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'replace_file' => ['nullable', 'file', new MediaDimensions, 'max:51200', 'mimetypes:image/jpeg,image/png,image/webp,image/avif,image/gif,video/mp4,video/webm'],
        ]);
        $before = $asset->toArray();
        $replacement = $request->file('replace_file');
        $attributes = [
            'name' => $data['name'],
            'alt_text' => $data['alt_text'] ?? null,
            'active' => $request->boolean('active'),
            'updated_by' => auth()->id(),
            'focal_point' => $this->point($data, 'focal_x', 'focal_y'),
            'crop' => $this->crop($data),
        ];
        $nextVersion = $this->nextVersion($asset);

        if ($replacement) {
            $dimensions = @getimagesize($replacement->getRealPath());
            $attributes += [
                'disk' => 'local',
                'path' => $replacement->store('site-media', 'local'),
                'mime_type' => $replacement->getMimeType(),
                'bytes' => (int) ($replacement->getSize() ?: 0) ?: null,
                'width' => (int) ($dimensions[0] ?? 0) ?: null,
                'height' => (int) ($dimensions[1] ?? 0) ?: null,
                'approval_status' => 'pending',
                'approved_at' => null,
                'approved_by' => null,
                'active' => false,
                'metadata' => array_merge((array) $asset->metadata, [
                    'original_name' => $replacement->getClientOriginalName(),
                    'security_scan' => 'queued_for_review',
                ]),
            ];
        }

        $asset->update($attributes);
        $variants = $replacement ? $derivatives->generate($asset, $nextVersion) : null;
        $this->recordCurrentVersion($asset->fresh(), $nextVersion, $variants);
        AuditTrail::record('site-media.updated', $asset, $before, $asset->fresh()->toArray());

        return back()->with('success', $replacement ? 'Media replaced and queued for approval.' : 'Media details updated.');
    }

    public function approve(MediaAsset $asset): RedirectResponse
    {
        $before = $asset->toArray();
        $asset->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'active' => true,
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('site-media.approved', $asset, $before, $asset->fresh()->toArray());

        return back()->with('success', 'Media approved for public delivery.');
    }

    public function reject(MediaAsset $asset): RedirectResponse
    {
        $before = $asset->toArray();
        $asset->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => null,
            'active' => false,
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('site-media.rejected', $asset, $before, $asset->fresh()->toArray());

        return back()->with('success', 'Media rejected and removed from public delivery.');
    }

    public function archive(string $asset): RedirectResponse
    {
        $media = $this->findAsset($asset);
        $before = $media->toArray();
        $metadata = array_merge((array) $media->metadata, [
            'previous_approval_status' => $media->approval_status,
            'previous_active' => (bool) $media->active,
        ]);
        $media->update([
            'approval_status' => 'archived',
            'active' => false,
            'metadata' => $metadata,
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('site-media.archived', $media, $before, $media->fresh()->toArray());

        return back()->with('success', 'Media archived and removed from public delivery.');
    }

    public function restore(string $asset): RedirectResponse
    {
        $media = $this->findAsset($asset);
        $before = $media->toArray();
        $wasTrashed = $media->trashed();
        if ($wasTrashed) {
            $media->restore();
            $media->refresh();
        }
        $metadata = (array) $media->metadata;
        $status = $metadata['previous_approval_status'] ?? $metadata['deleted_approval_status'] ?? $media->approval_status;
        $active = $metadata['previous_active'] ?? $metadata['deleted_active'] ?? $media->active;
        if ($media->approval_status === 'archived' || $wasTrashed) {
            $media->update([
                'approval_status' => in_array($status, self::STATUSES, true) && $status !== 'archived' ? $status : 'pending',
                'active' => (bool) $active && $status === 'approved',
                'metadata' => array_diff_key($metadata, array_flip([
                    'previous_approval_status', 'previous_active', 'deleted_approval_status', 'deleted_active',
                ])),
                'updated_by' => auth()->id(),
            ]);
        }
        AuditTrail::record('site-media.restored', $media, $before, $media->fresh()->toArray());

        return redirect()->route('admin.site-media.index')->with('success', 'Media restored to its previous approval state.');
    }

    public function restoreVersion(string $asset, int $version): RedirectResponse
    {
        $media = $this->findAsset($asset);
        abort_if($media->trashed(), 404);
        $snapshot = MediaAssetVersion::query()
            ->where('media_asset_id', $media->id)
            ->where('version', $version)
            ->firstOrFail();
        abort_unless($this->storedFileExists($snapshot->disk, $snapshot->path), 422, 'The selected media version is no longer available.');

        $before = $media->toArray();
        $nextVersion = $this->nextVersion($media);
        $metadata = array_merge((array) $media->metadata, (array) $snapshot->metadata, [
            'restored_from_version' => $version,
            'security_scan' => 'queued_for_review',
        ]);
        $media->update([
            'disk' => $snapshot->disk,
            'path' => $snapshot->path,
            'mime_type' => $snapshot->mime_type,
            'bytes' => $snapshot->bytes,
            'width' => $snapshot->width,
            'height' => $snapshot->height,
            'alt_text' => $snapshot->alt_text,
            'focal_point' => $snapshot->focal_point,
            'crop' => $snapshot->crop,
            'responsive_variants' => $snapshot->responsive_variants,
            'metadata' => $metadata,
            'approval_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'active' => false,
            'updated_by' => auth()->id(),
        ]);
        $this->recordCurrentVersion($media->fresh(), $nextVersion, null);
        AuditTrail::record('site-media.version-restored', $media, $before, [
            ...$media->fresh()->toArray(),
            'restored_version' => $version,
        ]);

        return back()->with('success', 'Media version restored and queued for approval.');
    }

    public function destroy(MediaAsset $asset): RedirectResponse
    {
        $before = $asset->toArray();
        $metadata = array_merge((array) $asset->metadata, [
            'deleted_approval_status' => $asset->approval_status,
            'deleted_active' => (bool) $asset->active,
        ]);
        $asset->updateQuietly(['metadata' => $metadata, 'active' => false, 'updated_by' => auth()->id()]);
        AuditTrail::record('site-media.deleted', $asset, $before, null);
        $asset->delete();

        return back()->with('success', 'Media moved to trash. Its source and versions are retained.');
    }

    public function permanentDestroy(string $asset, MediaAssetUsageService $usage): RedirectResponse
    {
        $media = $this->findAsset($asset);
        abort_unless($media->trashed(), 404);
        $references = $usage->for($media);
        if ($references !== []) {
            return back()->withErrors(['media' => 'This asset is still referenced by '.count($references).' public or managed record(s). Remove those references before permanent deletion.']);
        }

        $before = $media->toArray();
        $versions = MediaAssetVersion::query()->where('media_asset_id', $media->id)->get();
        $this->deleteStoredFiles($media, $versions);
        AuditTrail::record('site-media.permanently-deleted', $media, $before, null);
        $media->forceDelete();

        return redirect()->route('admin.site-media.index', ['view' => 'trash'])->with('success', 'Media permanently deleted.');
    }

    private function findAsset(string $uuid): MediaAsset
    {
        abort_unless(Str::isUuid($uuid), 404);

        return MediaAsset::withTrashed()
            ->visibleToCurrentCompany()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function recordCurrentVersion(MediaAsset $asset, int $version, ?array $variants): void
    {
        if ($variants !== null) {
            $asset->responsive_variants = $variants ?: null;
        }
        $metadata = (array) $asset->metadata;
        $metadata['current_version'] = $version;
        $asset->metadata = $metadata;
        $asset->saveQuietly();

        $snapshotMetadata = $metadata;
        unset($snapshotMetadata['current_version']);
        MediaAssetVersion::updateOrCreate(
            ['media_asset_id' => $asset->id, 'version' => $version],
            [
                'disk' => $asset->disk ?: 'local',
                'path' => $asset->path,
                'mime_type' => $asset->mime_type,
                'bytes' => $asset->bytes,
                'width' => $asset->width,
                'height' => $asset->height,
                'alt_text' => $asset->alt_text,
                'focal_point' => $asset->focal_point,
                'crop' => $asset->crop,
                'responsive_variants' => $asset->responsive_variants,
                'metadata' => $snapshotMetadata,
                'created_by' => auth()->id() ?: $asset->created_by,
            ],
        );
    }

    private function nextVersion(MediaAsset $asset): int
    {
        $current = (int) data_get($asset->metadata, 'current_version', 0);
        $latest = (int) (MediaAssetVersion::query()->where('media_asset_id', $asset->id)->max('version') ?: 0);

        return max($current, $latest) + 1;
    }

    private function point(array $data, string $xKey, string $yKey): ?array
    {
        if (($data[$xKey] ?? null) === null && ($data[$yKey] ?? null) === null) {
            return null;
        }

        return ['x' => (float) ($data[$xKey] ?? 50), 'y' => (float) ($data[$yKey] ?? 50)];
    }

    private function crop(array $data): ?array
    {
        if (! filled($data['crop_mode'] ?? null)
            && ($data['crop_x'] ?? null) === null
            && ($data['crop_y'] ?? null) === null
            && ($data['crop_width'] ?? null) === null
            && ($data['crop_height'] ?? null) === null) {
            return null;
        }

        return [
            'mode' => $data['crop_mode'] ?? 'cover',
            'x' => (float) ($data['crop_x'] ?? 0),
            'y' => (float) ($data['crop_y'] ?? 0),
            'width' => (float) ($data['crop_width'] ?? 100),
            'height' => (float) ($data['crop_height'] ?? 100),
        ];
    }

    private function storedFileExists(mixed $disk, mixed $path): bool
    {
        return in_array($disk, ['local', 'public'], true)
            && $this->safePath($path)
            && Storage::disk($disk)->exists($path);
    }

    private function deleteStoredFiles(MediaAsset $asset, $versions): void
    {
        $files = collect([
            ['disk' => $asset->disk, 'path' => $asset->path],
            ...$this->variantFiles($asset->disk, $asset->responsive_variants),
        ]);
        foreach ($versions as $version) {
            $files->push(['disk' => $version->disk, 'path' => $version->path]);
            foreach ($this->variantFiles($version->disk, $version->responsive_variants) as $file) {
                $files->push($file);
            }
        }

        $files->unique(fn (array $file): string => ($file['disk'] ?? '').'|'.($file['path'] ?? ''))
            ->each(function (array $file): void {
                if ($this->storedFileExists($file['disk'] ?? null, $file['path'] ?? null)) {
                    Storage::disk($file['disk'])->delete($file['path']);
                }
            });
    }

    private function variantFiles(mixed $disk, mixed $variants): array
    {
        if (! is_array($variants)) {
            return [];
        }

        return collect($variants)->map(function (mixed $variant) use ($disk): ?array {
            $path = is_array($variant) ? ($variant['path'] ?? null) : $variant;

            return $this->safePath($path) ? ['disk' => $disk, 'path' => $path] : null;
        })->filter()->values()->all();
    }

    private function safePath(mixed $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! Str::startsWith($path, ['/','\\'])
            && ! str_contains($path, '..')
            && ! preg_match('/\A(?:https?:)?\/\//i', $path);
    }
}
