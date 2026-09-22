<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteLayoutVersion;
use App\Services\SiteLayoutVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HeaderFooterManagerController extends Controller
{
    private const LOGOS = [
        '/assets/logo/logo_one_line.png',
        '/assets/logo/logo_two_line.png',
    ];

    public function index(Request $request, SiteLayoutVersionService $layouts): View
    {
        Gate::authorize('viewAny', SiteLayoutVersion::class);

        $locale = (string) ($request->query('locale') ?: app()->getLocale() ?: 'en');
        $company = $layouts->currentCompany();
        $query = $layouts->query($company, 'production', $locale);

        $draft = (clone $query)
            ->where('status', SiteLayoutVersion::STATUS_DRAFT)
            ->latest('version')
            ->first();

        $active = (clone $query)
            ->where('status', SiteLayoutVersion::STATUS_ACTIVE)
            ->latest('version')
            ->first();

        $source = $draft ?: $active;
        $regions = is_array($source?->regions) ? $source->regions : $layouts->defaults();

        return view('admin.pages.header-footer-manager', [
            'draft' => $draft,
            'active' => $active,
            'regions' => $regions,
            'locale' => $locale,
            'approvedLogos' => self::LOGOS,
        ]);
    }

    public function save(Request $request, SiteLayoutVersionService $layouts): RedirectResponse
    {
        $data = $request->validate([
            'header_logo' => ['required', Rule::in(self::LOGOS)],
            'header_logo_alt' => ['required', 'string', 'max:180'],
            'navigation' => ['nullable', 'array', 'max:30'],
            'navigation.*.label' => ['required', 'string', 'max:120'],
            'navigation.*.href' => ['required', 'string', 'max:2048'],
            'navigation.*.enabled' => ['nullable', 'boolean'],

            'footer_logo' => ['required', Rule::in(self::LOGOS)],
            'footer_logo_alt' => ['required', 'string', 'max:180'],
            'footer_brand_description' => ['nullable', 'string', 'max:500'],
            'footer_copyright_text' => ['nullable', 'string', 'max:240'],
            'footer_manufacturing_text' => ['nullable', 'string', 'max:240'],
            'footer_columns' => ['nullable', 'array', 'max:12'],
            'footer_columns.*.title' => ['required', 'string', 'max:120'],
            'footer_columns.*.links' => ['nullable', 'array', 'max:30'],
            'footer_columns.*.links.*.label' => ['required', 'string', 'max:120'],
            'footer_columns.*.links.*.href' => ['required', 'string', 'max:2048'],
            'footer_legal_links' => ['nullable', 'array', 'max:20'],
            'footer_legal_links.*.label' => ['required', 'string', 'max:120'],
            'footer_legal_links.*.href' => ['required', 'string', 'max:2048'],
        ]);

        $locale = (string) ($request->input('locale') ?: app()->getLocale() ?: 'en');
        $draft = $this->workingDraft($layouts, $locale);

        Gate::authorize('update', $draft);

        $regions = is_array($draft->regions) ? $draft->regions : $layouts->defaults();

        data_set($regions, 'header.logo.path', $data['header_logo']);
        data_set($regions, 'header.logo.alt', trim($data['header_logo_alt']));
        data_set($regions, 'header.primary_menu', collect($data['navigation'] ?? [])->values()->map(
            fn (array $item): array => [
                'label' => trim((string) $item['label']),
                'href' => trim((string) $item['href']),
                'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]
        )->all());

        data_set($regions, 'footer.logo.path', $data['footer_logo']);
        data_set($regions, 'footer.logo.alt', trim($data['footer_logo_alt']));
        data_set($regions, 'footer.brand_description', $this->nullableTrim($data['footer_brand_description'] ?? null));
        data_set($regions, 'footer.copyright_text', $this->nullableTrim($data['footer_copyright_text'] ?? null));
        data_set($regions, 'footer.manufacturing_text', $this->nullableTrim($data['footer_manufacturing_text'] ?? null));
        data_set($regions, 'footer.columns', collect($data['footer_columns'] ?? [])->values()->map(
            fn (array $column): array => [
                'title' => trim((string) $column['title']),
                'links' => collect($column['links'] ?? [])->values()->map(
                    fn (array $link): array => [
                        'label' => trim((string) $link['label']),
                        'href' => trim((string) $link['href']),
                    ]
                )->all(),
            ]
        )->all());
        data_set($regions, 'footer.legal_links', collect($data['footer_legal_links'] ?? [])->values()->map(
            fn (array $link): array => [
                'label' => trim((string) $link['label']),
                'href' => trim((string) $link['href']),
            ]
        )->all());

        $layouts->updateDraft($draft, [
            'name' => $draft->name,
            'regions' => $regions,
            'notes' => 'Managed from Header & Footer Manager.',
        ]);

        return redirect()->route('admin.pages.header-footer', ['locale' => $locale])
            ->with('success', 'Header and footer draft saved. Review it, then publish when ready.');
    }

    public function publish(SiteLayoutVersion $layout, SiteLayoutVersionService $layouts): RedirectResponse
    {
        Gate::authorize('update', $layout);
        Gate::authorize('activate', $layout);

        abort_unless($layout->status === SiteLayoutVersion::STATUS_DRAFT, 409, 'Only a draft can be published from Header & Footer Manager.');

        $layout = $layouts->validateLayout($layout);
        $layout = $layouts->submitForApproval($layout);
        $layout = $layouts->approve($layout);
        $layout = $layouts->activate($layout);

        return redirect()->route('admin.pages.header-footer', ['locale' => $layout->locale])
            ->with('success', 'Header and footer changes published to the public website.');
    }

    private function workingDraft(SiteLayoutVersionService $layouts, string $locale): SiteLayoutVersion
    {
        $company = $layouts->currentCompany();
        $query = $layouts->query($company, 'production', $locale);

        $draft = (clone $query)
            ->where('status', SiteLayoutVersion::STATUS_DRAFT)
            ->latest('version')
            ->first();

        if ($draft) {
            return $draft;
        }

        Gate::authorize('create', SiteLayoutVersion::class);

        $active = (clone $query)
            ->where('status', SiteLayoutVersion::STATUS_ACTIVE)
            ->latest('version')
            ->first();

        return $layouts->createDraft([
            'company' => $company,
            'name' => 'Header & Footer Manager Draft',
            'environment' => 'production',
            'locale' => $locale,
            'regions' => is_array($active?->regions) ? $active->regions : $layouts->defaults(),
            'notes' => 'Created from Header & Footer Manager.',
        ]);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
