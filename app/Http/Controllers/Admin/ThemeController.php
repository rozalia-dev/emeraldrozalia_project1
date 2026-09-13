<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeVersion;
use App\Services\ThemeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function index(Request $request, ThemeVersionService $themes): View
    {
        Gate::authorize('viewAny', ThemeVersion::class);
        $environment = $themes->currentEnvironment($request);
        $locale = $themes->currentLocale();
        $versions = $themes->versions(null, $environment, $locale);
        if ($request->filled('version') && ! $versions->contains('uuid', (string) $request->query('version'))) {
            abort(404);
        }
        $selected = $versions->firstWhere('uuid', (string) $request->query('version')) ?: $versions->first();

        return view('admin.settings.theme', [
            'versions' => $versions,
            'selected' => $selected,
            'environment' => $environment,
            'locale' => $locale,
            'defaults' => $themes->defaults(),
            'assetReferences' => $themes->defaultAssetReferences(),
        ]);
    }

    public function store(Request $request, ThemeVersionService $themes): RedirectResponse
    {
        Gate::authorize('create', ThemeVersion::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'environment' => ['required', 'in:production,staging,development'],
            'locale' => ['required', 'string', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tokens' => ['required', 'array'],
        ]);
        $theme = $themes->createDraft($data);

        return redirect()->route('admin.settings.theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', 'Theme draft created. Validate it before requesting approval.');
    }

    public function update(Request $request, ThemeVersion $theme, ThemeVersionService $themes): RedirectResponse
    {
        $this->assertCurrentContext($theme);
        Gate::authorize('update', $theme);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tokens' => ['required', 'array'],
        ]);
        $theme = $themes->updateDraft($theme, $data);

        return redirect()->route('admin.settings.theme.index', ['version' => $theme->uuid])->with('success', 'Theme draft saved. Validate it before requesting approval.');
    }

    public function action(Request $request, ThemeVersion $theme, string $action, ThemeVersionService $themes): RedirectResponse
    {
        abort_unless(in_array($action, ['validate', 'submit', 'approve', 'activate', 'disable', 'rollback'], true), 404);
        $this->assertCurrentContext($theme);
        Gate::authorize($action, $theme);
        $theme = match ($action) {
            'validate' => $themes->validateTheme($theme),
            'submit' => $themes->submitForApproval($theme),
            'approve' => $themes->approve($theme),
            'activate' => $themes->activate($theme),
            'disable' => $themes->disable($theme),
            'rollback' => $themes->rollback($theme),
            default => abort(404),
        };

        return redirect()->route('admin.settings.theme.index', ['version' => $theme->uuid])->with('success', 'Theme '.$action.' action completed and audited.');
    }

    private function assertCurrentContext(ThemeVersion $theme): void
    {
        $companyId = session('company_id');

        abort_unless($companyId === null || (int) $theme->company_id === (int) $companyId, 404);
    }
}
