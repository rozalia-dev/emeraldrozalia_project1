<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MediaAssetUsageService
{
    /**
     * Return only current-tenant references so the library never exposes a
     * different company's page or campaign to an administrator.
     *
     * @return list<array{label: string, detail: string, href: string, kind: string}>
     */
    public function for(MediaAsset $asset): array
    {
        $uuid = (string) $asset->uuid;
        $uses = [];

        $this->addRows($uses, 'banners', 'media_uuid', $uuid, 'Banner', 'title', 'admin.banners.index', 'selected');
        $this->addRows($uses, 'product_collections', 'media_uuid', $uuid, 'Collection', 'name', 'admin.collections.index', 'selected');

        if (Schema::hasTable('page_sections')) {
            $sections = DB::table('page_sections')
                ->join('content_pages', 'content_pages.id', '=', 'page_sections.content_page_id')
                ->where('page_sections.media_uuid', $uuid)
                ->whereNull('content_pages.deleted_at')
                ->get(['page_sections.id', 'page_sections.label', 'content_pages.id as page_id', 'content_pages.title']);
            foreach ($sections as $section) {
                $this->add($uses, 'Page section', (string) ($section->label ?: $section->title), route('admin.pages.edit', $section->page_id), 'page');
            }

            $itemSections = DB::table('page_sections')
                ->join('content_pages', 'content_pages.id', '=', 'page_sections.content_page_id')
                ->whereNull('content_pages.deleted_at')
                ->get(['page_sections.id', 'page_sections.settings', 'content_pages.id as page_id', 'content_pages.title']);
            foreach ($itemSections as $section) {
                $settings = is_array($section->settings) ? $section->settings : json_decode((string) $section->settings, true);
                if (! is_array($settings) || ! $this->containsUuid($settings, $uuid)) {
                    continue;
                }
                $this->add($uses, 'Page section item', (string) $section->title, route('admin.pages.edit', $section->page_id), 'page');
            }
        }

        if (Schema::hasTable('theme_versions')) {
            $themes = DB::table('theme_versions');
            $this->scopeTenant($themes, 'theme_versions');
            foreach ($themes->get(['uuid', 'name', 'asset_references']) as $theme) {
                $references = is_array($theme->asset_references) ? $theme->asset_references : json_decode((string) $theme->asset_references, true);
                if (is_array($references) && $this->containsUuid($references, $uuid)) {
                    $this->add($uses, 'Theme version', (string) $theme->name, route('admin.settings.theme.index', ['version' => $theme->uuid]), 'theme');
                }
            }
        }

        return collect($uses)->unique(fn (array $use): string => $use['href'].'|'.$use['label'])->values()->all();
    }

    private function addRows(array &$uses, string $table, string $column, string $uuid, string $label, string $nameColumn, string $routeName, string $queryKey): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $query = DB::table($table)->where($column, $uuid);
        $this->scopeTenant($query, $table);
        foreach ($query->get(['id', $nameColumn]) as $row) {
            $this->add($uses, $label, (string) ($row->{$nameColumn} ?: $label), route($routeName, [$queryKey => $row->id]), strtolower($label));
        }
    }

    private function add(array &$uses, string $label, string $detail, string $href, string $kind): void
    {
        $uses[] = ['label' => $label, 'detail' => $detail, 'href' => $href, 'kind' => $kind];
    }

    private function scopeTenant($query, string $table): void
    {
        if (! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        $companyId = session('company_id');
        $query->where(function ($scope) use ($companyId): void {
            if ($companyId !== null) {
                $scope->whereNull('company_id')->orWhere('company_id', (int) $companyId);
            } else {
                $scope->whereNull('company_id');
            }
        });
    }

    private function containsUuid(mixed $value, string $uuid): bool
    {
        if (is_string($value)) {
            return $value === $uuid;
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $nested) {
            if ($this->containsUuid($nested, $uuid)) {
                return true;
            }
        }

        return false;
    }
}
