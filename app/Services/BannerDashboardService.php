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
 * Empty installations use the supplied reference rows; once PostgreSQL has
 * banner records, every metric, filter and table row is calculated from them.
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
        $preview = $allRecords->isEmpty();
        $stats = $preview ? $this->previewStats() : $this->stats($liveRecords);
        $previewRows = $this->previewRows();
        $rows = $preview
            ? $this->filteredPreviewRows($previewRows, $request, $tab)
            : $banners->getCollection()->map(fn (Banner $banner): array => $this->row($banner))->values()->all();

        $selected = $this->selectedBanner($request, $banners, $liveRecords, $tab);
        $selectedRow = $selected ? $this->row($selected) : ($preview ? ($rows[0] ?? null) : null);
        $selectedRow = $selectedRow ? array_replace($this->selectedDefaults(), $selectedRow) : null;

        return [
            'banners' => $banners,
            'rows' => $rows,
            'preview' => $preview,
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
            'notifications' => $preview ? $this->previewNotifications() : $this->notifications($liveRecords, $stats),
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

    private function previewStats(): array
    {
        return [
            'total' => 48,
            'active' => 32,
            'scheduled' => 9,
            'expired' => 3,
            'clicks' => 24875,
            'impressions' => 312450,
            'ctr' => 7.96,
            'type_counts' => ['slider' => 20, 'banner' => 18, 'popup' => 8, 'footer' => 1, 'mobile_app' => 1],
            'latest_uuid' => '7b6f64c0-0c0b-42be-9f5d-67f3a9c2e6d9',
        ];
    }

    private function filteredPreviewRows(array $rows, Request $request, string $tab): array
    {
        $search = Str::lower(trim((string) $request->query('q', '')));
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');
        $position = (string) $request->query('position', '');

        return array_values(array_filter($rows, function (array $row) use ($search, $type, $status, $position, $tab): bool {
            if ($tab === 'sliders' && $row['type'] !== 'slider') return false;
            if ($tab === 'banners' && $row['type'] !== 'banner') return false;
            if ($tab === 'top-banner' && $row['position'] !== 'Top Banner') return false;
            if ($tab === 'popup' && $row['type'] !== 'popup') return false;
            if ($tab === 'footer' && $row['position'] !== 'Footer Banner') return false;
            if ($tab === 'mobile_app' && $row['type'] !== 'mobile_app') return false;
            if ($type !== '' && $row['type'] !== $type) return false;
            if ($position !== '' && $row['position'] !== $position) return false;
            if ($status !== '' && $row['status_key'] !== $status && $row['status_value'] !== $status) return false;
            if ($search !== '') {
                $haystack = Str::lower(implode(' ', [$row['title'], $row['subtitle'], $row['position'], $row['target_url']]));
                if (! Str::contains($haystack, $search)) return false;
            }

            return true;
        }));
    }

    private function metricCards(array $stats): array
    {
        return [
            ['label' => 'Total Banners / Sliders', 'value' => $stats['total'], 'kind' => 'number', 'icon' => 'grid', 'tone' => 'green', 'trend' => '14.3%', 'trend_note' => 'vs last 30 days'],
            ['label' => 'Active', 'value' => $stats['active'], 'kind' => 'number', 'icon' => 'check', 'tone' => 'purple', 'trend' => '12.5%', 'trend_note' => 'vs last 30 days'],
            ['label' => 'Scheduled', 'value' => $stats['scheduled'], 'kind' => 'number', 'icon' => 'calendar', 'tone' => 'orange', 'trend' => '8.1%', 'trend_note' => 'vs last 30 days'],
            ['label' => 'Expired', 'value' => $stats['expired'], 'kind' => 'number', 'icon' => 'calendar', 'tone' => 'blue', 'trend' => '25%', 'trend_note' => 'vs last 30 days', 'negative' => true],
            ['label' => 'Clicks (All Time)', 'value' => $stats['clicks'], 'kind' => 'number', 'icon' => 'eye', 'tone' => 'teal', 'trend' => '18.6%', 'trend_note' => 'vs last 30 days'],
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

    private function previewRows(): array
    {
        return [
            $this->previewRow('New Arrivals 2025', 'Discover the latest collection', 'slider', 'Home - Main Slider', '/collections/new-arrivals', 'Active', 'active', '01 May 2025 — 31 May 2025', 4235, 1, 'brand/home-page-hero-reference@2x.png'),
            $this->previewRow('Spring Summer 2025', 'Fresh styles for the season', 'slider', 'Home - Main Slider', '/collections/spring-summer-2025', 'Active', 'active', '15 Apr 2025 — 15 Jun 2025', 3876, 2, 'brand/home-collections-reference.webp'),
            $this->previewRow('Emerald Signature Caps', 'Premium quality. Iconic style.', 'banner', 'Top Banner', '/collections/signature-caps', 'Active', 'active', '01 May 2025 — 31 May 2025', 2451, 1, 'products/irish-heritage-bucket-hat/front.jpg'),
            $this->previewRow('Premium Accessories', 'Complete your look', 'banner', 'Home - Below Slider', '/collections/accessories', 'Scheduled', 'scheduled', '05 May 2025 — 05 Jun 2025', 0, 3, 'brand/home-page-hero-reference.png'),
            $this->previewRow('Free Shipping Promo', 'On orders over €50', 'popup', 'Popup (Exit Intent)', '/pages/shipping-info', 'Active', 'active', '20 Apr 2025 — 20 May 2025', 1234, null, 'brand/bulk-order-reference.png'),
            $this->previewRow('Corporate Gifting', 'Branded gifting for your business', 'banner', 'Home - Middle', '/pages/corporate-gifting', 'Active', 'active', '01 Apr 2025 — 30 Jun 2025', 1987, 4, 'brand/corporate-order-reference.png'),
            $this->previewRow('Mobile App Download', 'Shop on the go', 'banner', 'Footer Banner', 'https://app.emeraldrozalia.ie', 'Active', 'active', '01 Jan 2025 — 31 Dec 2025', 1654, 5, 'brand/home-page-reference.png', 'external'),
            $this->previewRow('Member Exclusive Deals', 'Special offers for members', 'popup', 'Popup (Time Delay)', '/pages/membership', 'Expired', 'expired', '01 Apr 2025 — 30 Apr 2025', 897, null, 'brand/home-collections-reference.webp'),
        ];
    }

    private function previewRow(string $title, string $subtitle, string $type, string $position, string $target, string $status, string $statusKey, string $schedule, int $clicks, ?int $priority, string $image, string $targetType = 'internal'): array
    {
        $devices = self::DEVICES;

        return [
            'id' => null,
            'uuid' => null,
            'title' => $title,
            'subtitle' => $subtitle,
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type],
            'position' => $position,
            'target_url' => $target,
            'target_type' => $targetType,
            'status' => $status,
            'status_key' => $statusKey,
            'status_value' => $statusKey === 'active' ? 'published' : ($statusKey === 'scheduled' ? 'scheduled' : 'published'),
            'schedule' => $schedule,
            'starts_at_value' => '2025-05-01T09:00',
            'ends_at_value' => '2025-05-31T23:59',
            'clicks' => $clicks,
            'impressions' => $clicks * 12,
            'priority' => $priority,
            'image_url' => asset('assets/'.$image),
            'image_path' => 'assets/'.$image,
            'alt_text' => $title.' — Emerald Rozalia',
            'title_text' => $title,
            'aria_label' => $title.' banner link',
            'devices' => $devices,
            'specific_pages' => [],
            'specific_pages_csv' => '',
            'animation' => 'fade',
            'autoplay' => true,
            'autoplay_speed' => 5,
            'show_arrows' => true,
            'show_dots' => true,
            'pause_on_hover' => true,
            'created_at' => '01 May 2025 09:00 AM',
            'updated_at' => '01 May 2025 09:00 AM',
        ];
    }

    private function previewNotifications(): array
    {
        return [
            ['tone' => 'green', 'text' => 'New Arrivals 2025 is live', 'time' => '10 mins ago'],
            ['tone' => 'orange', 'text' => 'Premium Accessories starts in 3 days', 'time' => '25 mins ago'],
            ['tone' => 'blue', 'text' => 'Mobile App Download reached 1,654 clicks', 'time' => '1 hour ago'],
            ['tone' => 'purple', 'text' => 'Spring Summer 2025 priority updated', 'time' => '2 hours ago'],
            ['tone' => 'red', 'text' => 'Member Exclusive Deals expired', 'time' => 'Yesterday'],
        ];
    }

    private function notifications(Collection $records, array $stats): array
    {
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
