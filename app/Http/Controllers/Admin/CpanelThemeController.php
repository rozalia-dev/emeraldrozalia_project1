<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeVersion;
use App\Services\CpanelThemeVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CpanelThemeController extends Controller
{
    public function index(Request $request, CpanelThemeVersionService $themes): View
    {
        $environment = $themes->currentEnvironment($request->query('environment'));
        $locale = $themes->currentLocale();
        $versions = $themes->versions(null, $environment, $locale);
        $selected = null;

        if ($request->filled('version')) {
            $selected = $versions->firstWhere('uuid', (string) $request->query('version'));
            abort_unless($selected, 404);
        }

        $selected ??= $versions->firstWhere('status', ThemeVersion::STATUS_DRAFT)
            ?? $versions->firstWhere('status', ThemeVersion::STATUS_ACTIVE)
            ?? $versions->first();

        return view('admin.settings.cpanel-theme', [
            'environment' => $environment,
            'locale' => $locale,
            'versions' => $versions,
            'selected' => $selected,
            'defaults' => $themes->defaults(),
            'activeSnapshot' => $themes->activeSnapshot(null, $environment, $locale),
        ]);
    }

    public function store(Request $request, CpanelThemeVersionService $themes): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'environment' => ['required', Rule::in(CpanelThemeVersionService::ENVIRONMENTS)],
            'locale' => ['required', 'string', 'max:12'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tokens' => ['required', 'array'],
        ]);

        $theme = $themes->createDraft($data, $request->user());

        return redirect()->route('admin.settings.cpanel-theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', 'cPanel theme draft created.');
    }

    public function update(Request $request, ThemeVersion $theme, CpanelThemeVersionService $themes): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tokens' => ['required', 'array'],
        ]);

        $theme = $themes->updateDraft($theme, $data, $request->user());

        return redirect()->route('admin.settings.cpanel-theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', 'cPanel theme draft saved.');
    }

    public function action(Request $request, ThemeVersion $theme, string $action, CpanelThemeVersionService $themes): RedirectResponse
    {
        abort_unless(in_array($action, ['validate', 'submit', 'approve', 'activate', 'disable', 'rollback'], true), 404);

        $theme = match ($action) {
            'validate' => $themes->validateTheme($theme, $request->user()),
            'submit' => $themes->submitForApproval($theme),
            'approve' => $themes->approve($theme, $request->user()),
            'activate' => $themes->activate($theme, $request->user()),
            'disable' => $themes->disable($theme, $request->user()),
            'rollback' => $themes->rollback($theme, $request->user()),
        };

        $messages = [
            'validate' => 'cPanel theme validated.',
            'submit' => 'cPanel theme submitted for approval.',
            'approve' => 'cPanel theme approved.',
            'activate' => 'cPanel theme activated.',
            'disable' => 'cPanel theme disabled. The approved default cPanel theme is now active.',
            'rollback' => 'cPanel theme rolled back to the selected approved snapshot.',
        ];

        return redirect()->route('admin.settings.cpanel-theme.index', [
            'environment' => $theme->environment,
            'version' => $theme->uuid,
        ])->with('success', $messages[$action]);
    }
}
