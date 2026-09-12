<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Banner, BannerRevision};
use App\Services\{AuditTrail, BannerDashboardService};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BannerController extends Controller
{
    private const SNAPSHOT_FIELDS = [
        'title', 'subtitle', 'type', 'position', 'target_url', 'target_type', 'status',
        'starts_at', 'ends_at', 'clicks', 'impressions', 'priority', 'image_disk', 'image_path',
        'device_visibility', 'specific_pages', 'alt_text', 'title_text', 'aria_label', 'animation',
        'autoplay', 'autoplay_speed', 'show_arrows', 'show_dots', 'pause_on_hover', 'settings',
    ];

    public function index(Request $request, BannerDashboardService $dashboard): View
    {
        return view('admin.banners.index', $dashboard->build($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data, &$banner): void {
            $banner = Banner::create($this->attributes($request, $data) + [
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $this->revision($banner, 'Created');
            AuditTrail::record('banners.created', $banner, null, $this->snapshot($banner));
        });

        return $this->redirectToBanner($banner)->with('success', 'Banner / slider created and saved.');
    }

    public function update(Request $request, string $banner): RedirectResponse
    {
        $banner = $this->findBanner($banner);
        $data = $this->validated($request, $banner);
        $before = $this->snapshot($banner);

        DB::transaction(function () use ($request, $data, $banner, $before): void {
            $banner->update($this->attributes($request, $data, $banner) + ['updated_by' => auth()->id()]);
            $this->revision($banner, 'Updated');
            AuditTrail::record('banners.updated', $banner, $before, $this->snapshot($banner));
        });

        return $this->redirectToBanner($banner)->with('success', 'Banner / slider updated.');
    }

    public function updateSettings(Request $request, string $banner): RedirectResponse
    {
        $banner = $this->findBanner($banner);
        $data = $request->validate([
            'animation' => ['required', 'string', 'max:30'],
            'autoplay' => ['nullable', 'boolean'],
            'autoplay_speed' => ['required', 'integer', 'min:1', 'max:3600'],
            'show_arrows' => ['nullable', 'boolean'],
            'show_dots' => ['nullable', 'boolean'],
            'pause_on_hover' => ['nullable', 'boolean'],
            'devices' => ['nullable', 'array'],
            'devices.*' => ['string', Rule::in(BannerDashboardService::DEVICES)],
            'specific_pages' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $this->snapshot($banner);

        DB::transaction(function () use ($request, $data, $banner, $before): void {
            $banner->update([
                'animation' => $data['animation'],
                'autoplay' => $request->boolean('autoplay'),
                'autoplay_speed' => (int) $data['autoplay_speed'],
                'show_arrows' => $request->boolean('show_arrows'),
                'show_dots' => $request->boolean('show_dots'),
                'pause_on_hover' => $request->boolean('pause_on_hover'),
                'device_visibility' => $this->devices($request->input('devices', [])),
                'specific_pages' => $this->pages($data['specific_pages'] ?? null),
                'updated_by' => auth()->id(),
            ]);
            $this->revision($banner, 'Display settings updated');
            AuditTrail::record('banners.settings.updated', $banner, $before, $this->snapshot($banner));
        });

        return $this->redirectToBanner($banner)->with('success', 'Banner display settings updated.');
    }

    public function duplicate(string $banner): RedirectResponse
    {
        $banner = $this->findBanner($banner);

        DB::transaction(function () use ($banner, &$copy): void {
            $copy = $banner->replicate();
            $copy->public_uuid = null;
            $copy->title = Str::limit($banner->title.' Copy', 180, '');
            $copy->status = 'draft';
            $copy->starts_at = null;
            $copy->ends_at = null;
            $copy->clicks = 0;
            $copy->impressions = 0;
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->save();
            $this->revision($copy, 'Duplicated');
            AuditTrail::record('banners.duplicated', $copy, null, $this->snapshot($copy));
        });

        return $this->redirectToBanner($copy)->with('success', 'Banner / slider duplicated as a draft.');
    }

    public function action(string $banner, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['publish', 'unpublish', 'schedule', 'archive', 'trash', 'restore'], true), 404);

        $banner = Banner::withTrashed()->where(function (Builder $query) use ($banner): void {
            if (ctype_digit($banner)) {
                $query->whereKey((int) $banner);
            } else {
                $query->where('public_uuid', $banner);
            }
        })->firstOrFail();
        $before = $this->snapshot($banner);

        DB::transaction(function () use ($banner, $action, $before): void {
            if ($action === 'restore') {
                $banner->restore();
            } elseif ($action === 'trash') {
                $banner->delete();
            } else {
                $attributes = match ($action) {
                    'publish' => ['status' => 'published'],
                    'unpublish' => ['status' => 'draft'],
                    'schedule' => [
                        'status' => 'scheduled',
                        'starts_at' => $banner->starts_at ?: now()->addDay(),
                    ],
                    'archive' => ['status' => 'archived'],
                };
                $banner->update($attributes + ['updated_by' => auth()->id()]);
            }

            $banner->refresh();
            if ($action !== 'trash') {
                $this->revision($banner, Str::headline($action));
            }
            AuditTrail::record(
                'banners.'.Str::replace('-', '_', $action),
                $banner,
                $before,
                $action === 'trash' ? null : $this->snapshot($banner),
            );
        });

        return redirect()->route('admin.banners.index')->with('success', 'Banner lifecycle action completed.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'bulk_action' => ['required', Rule::in(['publish', 'unpublish', 'schedule', 'archive', 'trash', 'restore', 'set_priority'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (array_unique(array_map('intval', $data['ids'])) as $id) {
                $banner = Banner::withTrashed()->find($id);
                if (! $banner) {
                    continue;
                }
                $before = $this->snapshot($banner);
                $action = $data['bulk_action'];

                if ($action === 'restore') {
                    $banner->restore();
                } elseif ($action === 'trash') {
                    $banner->delete();
                } elseif ($action === 'set_priority') {
                    $banner->update(['priority' => (int) ($data['priority'] ?? 1), 'updated_by' => auth()->id()]);
                } else {
                    $attributes = match ($action) {
                        'publish' => ['status' => 'published'],
                        'unpublish' => ['status' => 'draft'],
                        'schedule' => ['status' => 'scheduled', 'starts_at' => $banner->starts_at ?: now()->addDay()],
                        'archive' => ['status' => 'archived'],
                    };
                    $banner->update($attributes + ['updated_by' => auth()->id()]);
                }

                $banner->refresh();
                if ($action !== 'trash') {
                    $this->revision($banner, 'Bulk '.Str::headline($action));
                }
                AuditTrail::record(
                    'banners.bulk.'.Str::replace('-', '_', $action),
                    $banner,
                    $before,
                    $action === 'trash' ? null : $this->snapshot($banner),
                );
            }
        });

        return back()->with('success', 'Bulk banner action completed.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']]);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be opened.']);
        }

        $created = 0;
        try {
            $header = fgetcsv($handle);
            $header = is_array($header) ? array_map(fn ($value): string => Str::snake(trim((string) $value)), $header) : [];
            $required = ['title', 'type', 'status'];
            if (array_diff($required, $header)) {
                throw ValidationException::withMessages(['file' => 'CSV headers must include title, type and status.']);
            }

            DB::transaction(function () use ($handle, $header, &$created): void {
                while (($values = fgetcsv($handle)) !== false) {
                    if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                        continue;
                    }
                    $row = array_pad(array_combine($header, array_pad($values, count($header), null)) ?: [], count($header), null);
                    $payload = $this->importPayload($row, $created + 2);
                    $banner = Banner::create($payload + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                    $this->revision($banner, 'Imported');
                    AuditTrail::record('banners.imported', $banner, null, $this->snapshot($banner));
                    $created++;
                    if ($created > 500) {
                        throw ValidationException::withMessages(['file' => 'Import is limited to 500 banners per upload.']);
                    }
                }
            });
        } finally {
            fclose($handle);
        }

        if ($created === 0) {
            throw ValidationException::withMessages(['file' => 'The CSV did not contain any banner rows.']);
        }

        return back()->with('success', $created.' banner(s) imported successfully.');
    }

    public function export(Request $request, BannerDashboardService $dashboard): StreamedResponse
    {
        $query = $dashboard->filteredQuery($request);
        $filename = 'emerald-rozalia-banners-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $dashboard, $request): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['UUID', 'Title', 'Subtitle', 'Type', 'Position', 'Target URL', 'Status', 'Starts At', 'Ends At', 'Clicks', 'Impressions', 'Priority']);
            foreach ($query->orderBy('id')->cursor() as $banner) {
                $row = $dashboard->row($banner);
                fputcsv($output, [
                    $row['uuid'], $row['title'], $row['subtitle'], $row['type_label'], $row['position'],
                    $row['target_url'], $row['status'], $row['starts_at_value'], $row['ends_at_value'],
                    $row['clicks'], $row['impressions'], $row['priority'],
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function restoreRevision(string $banner, string $revision): RedirectResponse
    {
        $banner = Banner::withTrashed()->where(function (Builder $query) use ($banner): void {
            if (ctype_digit($banner)) {
                $query->whereKey((int) $banner);
            } else {
                $query->where('public_uuid', $banner);
            }
        })->firstOrFail();
        $revision = BannerRevision::where('banner_id', $banner->id)->where(function (Builder $query) use ($revision): void {
            if (ctype_digit($revision)) {
                $query->whereKey((int) $revision);
            } else {
                $query->where('uuid', $revision);
            }
        })->firstOrFail();
        $before = $this->snapshot($banner);
        $snapshot = (array) $revision->snapshot;

        DB::transaction(function () use ($banner, $snapshot, $before): void {
            $attributes = collect(self::SNAPSHOT_FIELDS)->mapWithKeys(function (string $field) use ($snapshot): array {
                return [$field => $snapshot[$field] ?? null];
            })->all();
            $banner->update($attributes + ['updated_by' => auth()->id()]);
            $this->revision($banner, 'Restored revision');
            AuditTrail::record('banners.revision.restored', $banner, $before, $this->snapshot($banner));
        });

        return $this->redirectToBanner($banner)->with('success', 'Banner revision restored.');
    }

    public function audit(string $banner): RedirectResponse
    {
        return redirect()->route('admin.banners.index', ['selected' => $this->findBanner($banner)->id, 'modal' => 'audit']);
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(BannerDashboardService::TYPES)],
            'position' => ['required', Rule::in(BannerDashboardService::POSITIONS)],
            'target_url' => ['nullable', 'string', 'max:500'],
            'target_type' => ['required', Rule::in(['internal', 'external'])],
            'status' => ['required', Rule::in(BannerDashboardService::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'],
            'devices' => ['nullable', 'array'],
            'devices.*' => ['string', Rule::in(BannerDashboardService::DEVICES)],
            'specific_pages' => ['nullable', 'string', 'max:2000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title_text' => ['nullable', 'string', 'max:180'],
            'aria_label' => ['nullable', 'string', 'max:180'],
            'animation' => ['nullable', 'string', 'max:30'],
            'autoplay' => ['nullable', 'boolean'],
            'autoplay_speed' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'show_arrows' => ['nullable', 'boolean'],
            'show_dots' => ['nullable', 'boolean'],
            'pause_on_hover' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(Request $request, array $data, ?Banner $banner = null): array
    {
        $path = array_key_exists('image_path', $data) ? $data['image_path'] : $banner?->image_path;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('banners', 'public');
        }

        return [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'type' => $data['type'],
            'position' => $data['position'],
            'target_url' => $data['target_url'] ?? null,
            'target_type' => $data['target_type'],
            'status' => $data['status'],
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
            'priority' => (int) $data['priority'],
            'image_disk' => 'public',
            'image_path' => $path,
            'device_visibility' => $this->devices($data['devices'] ?? []),
            'specific_pages' => $this->pages($data['specific_pages'] ?? null),
            'alt_text' => $data['alt_text'] ?? null,
            'title_text' => $data['title_text'] ?? null,
            'aria_label' => $data['aria_label'] ?? null,
            'animation' => $data['animation'] ?? 'fade',
            'autoplay' => $request->boolean('autoplay'),
            'autoplay_speed' => (int) ($data['autoplay_speed'] ?? 5),
            'show_arrows' => $request->boolean('show_arrows'),
            'show_dots' => $request->boolean('show_dots'),
            'pause_on_hover' => $request->boolean('pause_on_hover'),
        ];
    }

    private function importPayload(array $row, int $line): array
    {
        $type = Str::snake(Str::lower(trim((string) ($row['type'] ?? ''))));
        $status = Str::snake(Str::lower(trim((string) ($row['status'] ?? ''))));
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '' || ! in_array($type, BannerDashboardService::TYPES, true) || ! in_array($status, BannerDashboardService::STATUSES, true)) {
            throw ValidationException::withMessages(['file' => 'Invalid title, type or status on CSV line '.$line.'.']);
        }

        try {
            $startsAt = filled($row['starts_at'] ?? null) ? Carbon::parse($row['starts_at']) : null;
            $endsAt = filled($row['ends_at'] ?? null) ? Carbon::parse($row['ends_at']) : null;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Invalid date on CSV line '.$line.'.']);
        }
        if ($startsAt && $endsAt && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages(['file' => 'End date must be after start date on CSV line '.$line.'.']);
        }

        return [
            'title' => Str::limit($title, 180, ''),
            'subtitle' => filled($row['subtitle'] ?? null) ? Str::limit(trim((string) $row['subtitle']), 255, '') : null,
            'type' => $type,
            'position' => in_array((string) ($row['position'] ?? ''), BannerDashboardService::POSITIONS, true) ? $row['position'] : 'Home - Main Slider',
            'target_url' => filled($row['target_url'] ?? null) ? Str::limit(trim((string) $row['target_url']), 500, '') : null,
            'target_type' => (($row['target_type'] ?? 'internal') === 'external') ? 'external' : 'internal',
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'priority' => max(1, min(999, (int) ($row['priority'] ?? 1))),
            'image_disk' => 'public',
            'image_path' => filled($row['image_path'] ?? null) ? Str::limit(trim((string) $row['image_path']), 500, '') : null,
            'device_visibility' => BannerDashboardService::DEVICES,
            'specific_pages' => [],
            'alt_text' => filled($row['alt_text'] ?? null) ? Str::limit(trim((string) $row['alt_text']), 255, '') : $title,
            'title_text' => filled($row['title_text'] ?? null) ? Str::limit(trim((string) $row['title_text']), 180, '') : $title,
            'aria_label' => filled($row['aria_label'] ?? null) ? Str::limit(trim((string) $row['aria_label']), 180, '') : $title.' banner link',
            'animation' => 'fade',
            'autoplay' => true,
            'autoplay_speed' => 5,
            'show_arrows' => true,
            'show_dots' => true,
            'pause_on_hover' => true,
        ];
    }

    private function devices(array|string|null $devices): array
    {
        $devices = is_string($devices) ? preg_split('/[,\s]+/', $devices, -1, PREG_SPLIT_NO_EMPTY) : ($devices ?? []);
        $devices = array_values(array_intersect(BannerDashboardService::DEVICES, array_map('strtolower', $devices)));

        return $devices ?: BannerDashboardService::DEVICES;
    }

    private function pages(?string $pages): array
    {
        if (! filled($pages)) {
            return [];
        }

        return collect(preg_split('/[,\n]+/', $pages, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($page): string => Str::limit(trim((string) $page), 180, ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function findBanner(string $value): Banner
    {
        return Banner::query()->where(function (Builder $query) use ($value): void {
            if (ctype_digit($value)) {
                $query->whereKey((int) $value);
            } else {
                $query->where('public_uuid', $value);
            }
        })->firstOrFail();
    }

    private function snapshot(Banner $banner): array
    {
        $current = Banner::withTrashed()->find($banner->getKey()) ?: $banner;

        return $current->only(self::SNAPSHOT_FIELDS);
    }

    private function revision(Banner $banner, string $reason): void
    {
        $banner->revisions()->create([
            'user_id' => auth()->id(),
            'version' => ((int) $banner->revisions()->max('version')) + 1,
            'snapshot' => $this->snapshot($banner),
            'reason' => $reason,
        ]);
    }

    private function redirectToBanner(Banner $banner): RedirectResponse
    {
        return redirect()->route('admin.banners.index', ['selected' => $banner->id, 'modal' => 'edit']);
    }
}
