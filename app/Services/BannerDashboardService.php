<?php

namespace App\Services;

use App\Models\{AuditLog, Banner};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Server-first data builder for the Website & Products banner workspace.
 * Metrics, filters, activity and table rows always come from Banner records.
 */
class BannerDashboardService
{
    public const TYPES = ['slider', 'banner', 'popup', 'footer', 'mobile_app'];

    public const TYPE_LABELS = [
        'slider' => 'Slider',
        'banner' => 'Banner',
        'popup' => 'Popup',
        'footer' => 'Footer',
        'mobile_app' => 'Mobile App',
    ];

    public const STATUSES = ['draft', 'in_review', 'scheduled', 'published', 'archived'];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'in_review' => 'In Review',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    public const POSITIONS = [
        'Home - Main Slider',
        'Home - Below Slider',
        'Home - Middle',
        'Top Banner',
        'Popup (Exit Intent)',
        'Popup (Time Delay)',
        'Footer Banner',
        'Mobile App Banner',
    ];

    public const DEVICES = ['desktop', 'tablet', 'mobile'];

    public function build(Request $request): array
    {
        $tab = $this->normaliseTab((string) $request->query('tab', 'all'));
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $banners = $this->filteredQuery($request, $tab)
            ->with('creator')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $allRecords = Banner::withTrashed()->latest('updated_at')->get();
        $liveRecords = $allRecords->filter(fn (Banner $banner): bool => ! $banner->trashed())->values();
        $rows = $banners->getCollection()
            ->map(fn (Banner $banner): array => $this->row($banner))
            ->values()
            ->all();

        $selected = $this->selectedBanner($request, $banners, $liveRecords, $tab);
        $selectedRow = $selected ? $this->row($selected) : null;
        $selectedRow = $selectedRow ? array_replace($this->selectedDefaults(), $selectedRow) : null;
        $stats = $this->stats($liveRecords);

        return [
            'banners' => $banners,
            'rows' => $rows,
            'isEmpty' => $allRecords->isEmpty(),
            'dataNote' => $allRecords->isEmpty()
                ? 'No banner records are stored yet. Metrics and activity will populate after the first saved banner.'
                : 'Metrics, activity and table rows are calculated from Banner records in the current company context.',
            'selectedBanner' => $selectedRow,
            'selectedId' => $selected?->id,
            'selectedUuid' => $selected?->public_uuid,
            'revisions' => $selected ? $selected->revisions()->with('user')->get() : collect(),
            'auditRows' => $selected ? $this->auditRows($selected) : collect(),
            'stats' => $stats,
            'metrics' => $this->metricCards($stats),
            'tabs' => $this->tabs(),
            'tab' => $tab,
            'types' => self::TYPE_LABELS,
            'statuses' => self::STATUSES,
            'statusLabels' => self::STATUS_LABELS,
            'positions' => self::POSITIONS,
            'devices' => self::DEVICES,
            'openModal' => in_array((string) $request->query('modal', ''), ['create', 'edit', 'preview', 'audit'], true)
                ? (string) $request->query('modal')
                : null,
            'search' => trim((string) $request->query('q', '')),
            'typeFilter' => (string) $request->query('type', ''),
            'statusFilter' => (string) $request->query('status', ''),
            'deviceFilter' => (string) $request->query('device', ''),
            'positionFilter' => (string) $request->query('position', ''),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
            'notifications' => $this->notifications($liveRecords, $stats),
        ];
    }

    public function filteredQuery(Request $request, ?string $tab = null): Builder
    {
        $tab ??= $this->normaliseTab((string) $request->query('tab', 'all'));
        $query = Banner::query();

        if ($tab === 'trash') {
            $query->withTrashed()->whereNotNull('banners.deleted_at');
        }

        if ($tab === 'sliders') {
            $query->where('type', 'slider');
        } elseif ($tab === 'banners') {
            $query->where('type', 'banner');
        } elseif ($tab === 'top-banner') {
            $query->where('position', 'Top Banner');
        } elseif ($tab === 'popup') {
            $query->where('type', 'popup');
        } elseif ($tab === 'footer') {
            $query->where('type', 'footer');
        } elseif ($tab === 'mobile_app') {
            $query->where('type', 'mobile_app');
        }

        $search = Str::lower(trim((string) $request->query('q', '')));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                foreach (['title', 'subtitle', 'position', 'target_url', 'alt_text'] as $column) {
                    $searchQuery->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.$search.'%']);
                }
            });
        }

        if (in_array((string) $request->query('type', ''), self::TYPES, true)) {
            $query->where('type', (string) $request->query('type'));
        }

        $status = (string) $request->query('status', '');
        if ($status === 'active') {
            $query->where('status', 'published')->where(function (Builder $active): void {
                $active->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })->where(function (Builder $active): void {
                $active->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        } elseif ($status === 'expired') {
            $query->where('status', 'published')->whereNotNull('ends_at')->where('ends_at', '<', now());
        } elseif (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $device = (string) $request->query('device', '');
        if (in_array($device, self::DEVICES, true)) {
            $query->whereJsonContains('device_visibility', $device);
        }

        if (in_array((string) $request->query('position', ''), self::POSITIONS, true)) {
            $query->where('position', (string) $request->query('position'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ends_at', '<=', (string) $request->query('date_to'));
        }

        return $query;
    }

    public function row(Banner $banner): array
    {
        $status = $this->displayStatus($banner);
        $devices = array_values(array_intersect(self::DEVICES, (array) $banner->device_visibility));
        $specificPages = array_values(array_filter((array) $banner->specific_pages, fn ($page): bool => filled($page)));

        return [
            'id' => $banner->id,
            'uuid' => $banner->public_uuid,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle ?: 'Emerald Rozalia website campaign',
            'type' => $banner->type,
            'type_label' => self::TYPE_LABELS[$banner->type] ?? Str::headline((string) $banner->type),
            'position' => $banner->position,
            'target_url' => $banner->target_url ?: '/',
            'target_type' => $banner->target_type,
            'status' => $status['label'],
            'status_key' => $status['key'],
            'status_value' => $banner->status,
            'schedule' => $this->scheduleLabel($banner->starts_at, $banner->ends_at),
            'starts_at_value' => $banner->starts_at?->format('Y-m-d\\TH:i'),
            'ends_at_value' => $banner->ends_at?->format('Y-m-d\\TH:i'),
            'clicks' => (int) $banner->clicks,
            'impressions' => (int) $banner->impressions,
            'priority' => (int) $banner->priority,
            'image_url' => $banner->imageUrl(),
            'image_path' => $banner->image_path,
            'alt_text' => $banner->alt_text ?: $banner->title,
            'title_text' => $banner->title_text ?: $banner->title,
            'aria_label' => $banner->aria_label ?: $banner->title.' banner link',
            'devices' => $devices ?: self::DEVICES,
            'specific_pages' => $specificPages,
            'specific_pages_csv' => implode(', ', $specificPages),
            'animation' => $banner->animation ?: 'fade',
            'autoplay' => (bool) $banner->autoplay,
            'autoplay_speed' => (int) $banner->autoplay_speed,
            'show_arrows' => (bool) $banner->show_arrows,
            'show_dots' => (bool) $banner->show_dots,
            'pause_on_hover' => (bool) $banner->pause_on_hover,
            'created_at' => $banner->created_at?->format('d M Y g:i A'),
            'updated_at' => $banner->updated_at?->format('d M Y g:i A'),
        ];
    }

    private function selectedBanner(Request $request, LengthAwarePaginator $banners, Collection $liveRecords, string $tab): ?Banner
    {
        $selected = null;
        $selectedValue = trim((string) $request->query('selected', ''));
        if ($selectedValue !== '') {
            $selected = Banner::withTrashed()->where(function (Builder $query) use ($selectedValue): void {
                if (ctype_digit($selectedValue)) {
                    $query->whereKey((int) $selectedValue);
                } else {
                    $query->where('public_uuid', $selectedValue);
                }
            })->first();
        }

        if (! $selected && $banners->first() instanceof Banner) {
            $selected = $banners->first();
        }

        if (! $selected && $tab !== 'trash') {
            $selected = $liveRecords->first();
        }

        return $selected;
    }

    private function stats(Collection $records): array
    {
        $active = $records->filter(fn (Banner $banner): bool => $this->displayStatus($banner)['key'] === 'active')->count();
        $scheduled = $records->filter(fn (Banner $banner): bool => $this->displayStatus($banner)['key'] === 'scheduled')->count();
        $expired = $records->filter(fn (Banner $banner): bool => $this->displayStatus($banner)['key'] === 'expired')->count();
        $clicks = (int) $records->sum('clicks');
        $impressions = (int) $records->sum('impressions');

        return [
            'total' => $records->count(),
            'active' => $active,
            'scheduled' => $scheduled,
            'expired' => $expired,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            'type_counts' => collect(self::TYPES)->mapWithKeys(fn (string $type): array => [$type => $records->where('type', $type)->count()])->all(),
            'latest_uuid' => $records->sortByDesc('created_at')->first()?->public_uuid,
        ];
    }

    private function metricCards(array $stats): array
    {
        return [
            ['label' => 'Total Banners / Sliders', 'value' => $stats['total'], 'kind' => 'number', 'icon' => 'grid', 'tone' => 'green', 'trend' => null, 'trend_note' => 'No comparison loaded'],
            ['label' => 'Active', 'value' => $stats['active'], 'kind' => 'number', 'icon' => 'check', 'tone' => 'purple', 'trend' => null, 'trend_note' => 'No comparison loaded'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'kind' => 'number', 'icon' => 'calendar', 'tone' => 'orange', 'trend' => null, 'trend_note' => 'No comparison loaded'],
            ['label' => 'Expired', 'value' => $stats['expired'], 'kind' => 'number', 'icon' => 'calendar', 'tone' => 'blue', 'trend' => null, 'trend_note' => 'No comparison loaded', 'negative' => true],
            ['label' => 'Clicks (All Time)', 'value' => $stats['clicks'], 'kind' => 'number', 'icon' => 'eye', 'tone' => 'teal', 'trend' => null, 'trend_note' => 'No comparison loaded'],
        ];
    }

    private function tabs(): array
    {
        return [
            'all' => 'All Banners / Sliders',
            'sliders' => 'Sliders',
            'banners' => 'Banners',
            'top-banner' => 'Top Banner',
            'popup' => 'Popup',
            'footer' => 'Footer',
            'mobile_app' => 'Mobile App',
        ];
    }

    private function normaliseTab(string $tab): string
    {
        return array_key_exists($tab, $this->tabs()) ? $tab : 'all';
    }

    private function displayStatus(Banner $banner): array
    {
        if ($banner->trashed()) {
            return ['label' => 'Trashed', 'key' => 'trashed'];
        }

        if ($banner->status === 'archived') {
            return ['label' => 'Archived', 'key' => 'archived'];
        }

        if ($banner->status === 'scheduled' || ($banner->status === 'published' && $banner->starts_at?->isFuture())) {
            return ['label' => 'Scheduled', 'key' => 'scheduled'];
        }

        if ($banner->status === 'published' && $banner->ends_at?->isPast()) {
            return ['label' => 'Expired', 'key' => 'expired'];
        }

        if ($banner->status === 'published') {
            return ['label' => 'Active', 'key' => 'active'];
        }

        return ['label' => self::STATUS_LABELS[$banner->status] ?? Str::headline((string) $banner->status), 'key' => Str::slug((string) $banner->status)];
    }

    private function scheduleLabel(?Carbon $startsAt, ?Carbon $endsAt): string
    {
        $from = $startsAt?->format('d M Y');
        $to = $endsAt?->format('d M Y');
        if ($from && $to) {
            return $from.' — '.$to;
        }

        return $from ?: ($to ? 'Until '.$to : 'Always on');
    }

    private function selectedDefaults(): array
    {
        return [
            'devices' => self::DEVICES,
            'specific_pages' => [],
            'specific_pages_csv' => '',
            'animation' => 'fade',
            'autoplay' => true,
            'autoplay_speed' => 5,
            'show_arrows' => true,
            'show_dots' => true,
            'pause_on_hover' => true,
            'alt_text' => '',
            'title_text' => '',
            'aria_label' => '',
            'target_url' => '/',
            'target_type' => 'internal',
        ];
    }

    private function notifications(Collection $records, array $stats): array
    {
        if ($records->isEmpty()) {
            return [['tone' => 'blue', 'text' => 'No banner activity recorded yet', 'time' => 'Awaiting first record']];
        }

        $scheduled = $records->filter(fn (Banner $banner): bool => $this->displayStatus($banner)['key'] === 'scheduled')->count();
        $expired = $stats['expired'];

        return [
            ['tone' => 'green', 'text' => $stats['active'].' banners are active', 'time' => 'Live now'],
            ['tone' => 'orange', 'text' => $scheduled.' banners are scheduled', 'time' => 'Upcoming'],
            ['tone' => 'blue', 'text' => number_format($stats['clicks']).' total clicks recorded', 'time' => 'All time'],
            ['tone' => 'purple', 'text' => 'Banner audit trail is available', 'time' => 'Secure log'],
            ['tone' => 'red', 'text' => $expired.' banners need review', 'time' => 'Attention'],
        ];
    }

    private function auditRows(Banner $banner): Collection
    {
        return AuditLog::query()
            ->where('subject_type', $banner->getMorphClass())
            ->where('subject_id', (string) $banner->id)
            ->latest('created_at')
            ->limit(8)
            ->with('user')
            ->get();
    }
}
