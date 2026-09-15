<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeVersion;
use App\Services\AdminAppearanceService;
use App\Services\ThemeQuickApplyService;
use App\Services\ThemeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function index(Request $request, ThemeVersionService $themes, AdminAppearanceService $appearance): View
    {
        Gate::authorize('viewAny', ThemeVersion::class);
        $environment = $themes->currentEnvironment($request);
        $locale = $themes->currentLocale();
        $versions = $themes->versions(null, $environment, $locale);
        if ($request->filled('version') && ! $versions->contains('uuid', (string) $request->query('version'))) {
            abort(404);
        }

        $active = $versions->firstWhere('status', ThemeVersion::STATUS_ACTIVE);
        $selected = $versions->firstWhere('uuid', (string) $request->query('version')) ?: $active ?: $versions->first();
        $effectiveTokens = $selected
            ? array_replace_recursive($themes->defaults(), (array) $selected->token_payload)
            : $themes->defaults();

        return view('admin.settings.theme-simple', [
            'versions' => $versions,
            'selected' => $selected,
            'active' => $active,
            'environment' => $environment,
            'locale' => $locale,
            'defaults' => $themes->defaults(),
            'effectiveTokens' => $effectiveTokens,
            'assetReferences' => $themes->defaultAssetReferences(),
            'cpanelTheme' => $appearance->current(),
            'cpanelThemeOptions' => $appearance->options(),
        ]);
    }

    public function apply(Request $request, ThemeQuickApplyService $quickApply): RedirectResponse
    {
        $this->authorizeInstantPublish($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'environment' => ['required', 'in:production,staging,development'],
            'locale' => ['required', 'string', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tokens' => ['required', 'array'],
        ]);

        $theme = $quickApply->apply($data, $request->user());

        return redirect()->route('admin.settings.theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', 'Theme applied globally. The new version is live on every public page that uses the Project 1 public theme contract.');
    }

    public function resetDefault(Request $request, ThemeVersionService $themes, ThemeQuickApplyService $quickApply): RedirectResponse
    {
        $this->authorizeInstantPublish($request);
        $environment = $themes->currentEnvironment($request);
        $theme = $quickApply->resetToDefault($environment, $themes->currentLocale(), $request->user());

        return redirect()->route('admin.settings.theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', 'Default Emerald Rozalia theme restored and applied globally.');
    }

    public function updateCpanelTheme(Request $request, AdminAppearanceService $appearance): RedirectResponse
    {
        $this->authorizeSettingsUpdate($request);
        $data = $request->validate(['cpanel_theme' => ['required', 'in:guide-dark,light']]);
        $appearance->update($data['cpanel_theme'], null, $request->user());

        return redirect()->route('admin.settings.theme.index')->with('success', 'cPanel appearance updated. This does not change the public website theme.');
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
        ])->with('success', 'Theme draft saved for advanced lifecycle use.');
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

        return redirect()->route('admin.settings.theme.index', ['version' => $theme->uuid])->with('success', 'Theme draft saved.');
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

    private function authorizeInstantPublish(Request $request): void
    {
        Gate::authorize('create', ThemeVersion::class);
        $user = $request->user();
        abort_unless($user && ($user->is_admin || ($user->hasPermission('settings.create') && $user->hasPermission('settings.approve'))), 403);
    }

    private function authorizeSettingsUpdate(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->is_admin || $user->hasPermission('settings.update')), 403);
    }

    private function assertCurrentContext(ThemeVersion $theme): void
    {
        $companyId = session('company_id');

        abort_unless($companyId === null || (int) $theme->company_id === (int) $companyId, 404);
    }
}
