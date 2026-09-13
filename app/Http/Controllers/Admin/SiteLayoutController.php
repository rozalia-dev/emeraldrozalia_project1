<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteLayoutVersion;
use App\Services\SiteLayoutVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteLayoutController extends Controller
{
    public function index(Request $request, SiteLayoutVersionService $layouts): View
    {
        Gate::authorize('viewAny', SiteLayoutVersion::class);
        $environment = (string) $request->query('environment', 'production');
        if (! in_array($environment, SiteLayoutVersionService::ENVIRONMENTS, true)) {
            $environment = 'production';
        }
        $locale = (string) ($request->query('locale') ?: app()->getLocale() ?: 'en');
        $versions = $layouts->versions(null, $environment, $locale);
        if ($request->filled('version') && ! $versions->contains('uuid', (string) $request->query('version'))) {
            abort(404);
        }
        $selected = $versions->firstWhere('uuid', (string) $request->query('version')) ?: $versions->first();

        return view('admin.pages.layouts', [
            'versions' => $versions,
            'selected' => $selected,
            'environment' => $environment,
            'locale' => $locale,
            'defaults' => $layouts->defaults(),
        ]);
    }

    public function store(Request $request, SiteLayoutVersionService $layouts): RedirectResponse
    {
        Gate::authorize('create', SiteLayoutVersion::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'environment' => ['required', 'in:production,staging,development'],
            'locale' => ['required', 'string', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'regions' => ['nullable', 'array'],
            'regions_json' => ['nullable', 'string', 'max:300000'],
        ]);
        $data['regions'] = $this->regions($request, $data['regions'] ?? null);
        $layout = $layouts->createDraft($data);

        return redirect()->route('admin.pages.layouts', ['environment' => $layout->environment, 'version' => $layout->uuid])
            ->with('success', 'Shared layout draft created. Validate it before requesting approval.');
    }

    public function update(Request $request, SiteLayoutVersion $layout, SiteLayoutVersionService $layouts): RedirectResponse
    {
        $this->assertCurrentContext($layout);
        Gate::authorize('update', $layout);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'regions' => ['nullable', 'array'],
            'regions_json' => ['nullable', 'string', 'max:300000'],
        ]);
        $data['regions'] = $this->regions($request, $data['regions'] ?? null);
        $layout = $layouts->updateDraft($layout, $data);

        return redirect()->route('admin.pages.layouts', ['version' => $layout->uuid])
            ->with('success', 'Shared layout draft saved. Validate it before requesting approval.');
    }

    private function regions(Request $request, ?array $regions): array
    {
        if ($regions !== null) {
            return $regions;
        }

        $json = trim((string) $request->input('regions_json', ''));
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'regions_json' => 'Enter valid JSON for the shared regions.',
            ]);
        }

        return $decoded;
    }

    public function action(Request $request, SiteLayoutVersion $layout, string $action, SiteLayoutVersionService $layouts): RedirectResponse
    {
        abort_unless(in_array($action, ['validate', 'submit', 'approve', 'activate', 'disable', 'rollback'], true), 404);
        $this->assertCurrentContext($layout);
        Gate::authorize($action, $layout);
        $layout = match ($action) {
            'validate' => $layouts->validateLayout($layout),
            'submit' => $layouts->submitForApproval($layout),
            'approve' => $layouts->approve($layout),
            'activate' => $layouts->activate($layout),
            'disable' => $layouts->disable($layout),
            'rollback' => $layouts->rollback($layout),
            default => abort(404),
        };

        return redirect()->route('admin.pages.layouts', ['version' => $layout->uuid])
            ->with('success', 'Shared layout '.$action.' action completed and audited.');
    }

    public function preview(SiteLayoutVersion $layout, SiteLayoutVersionService $layouts): View
    {
        $this->assertCurrentContext($layout);
        Gate::authorize('view', $layout);

        return view('site.home', [
            ...app(\App\Http\Controllers\SiteController::class)->homeData(),
            'siteLayoutPreview' => $layouts->previewSnapshot($layout),
            'layoutPreviewMode' => true,
        ]);
    }

    private function assertCurrentContext(SiteLayoutVersion $layout): void
    {
        $companyId = session('company_id');

        abort_unless($companyId === null || (int) $layout->company_id === (int) $companyId, 404);
    }
}
