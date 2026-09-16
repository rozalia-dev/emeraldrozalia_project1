<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationTemplate;
use App\Services\CommunicationTemplateCatalogService;
use App\Services\CommunicationTemplateRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class EmailTemplateUiController extends Controller
{
    public function __construct(
        private readonly CommunicationTemplateCatalogService $catalog,
        private readonly CommunicationTemplateRoleService $roles,
    ) {
    }

    public function page(Request $request, CommunicationCenterController $center): View
    {
        Gate::authorize('viewAny', CommunicationTemplate::class);
        $this->catalog->ensureForCurrentCompany();

        $view = $center->show($request, 'email-templates');
        $query = CommunicationTemplate::query()
            ->forCurrentCompany()
            ->where('channel', 'email')
            ->whereIn('id', $this->roles->visibleTemplateIds($request->user()))
            ->latest('updated_at')
            ->latest('id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function ($templates) use ($needle): void {
                $templates->where('name', 'like', $needle)
                    ->orWhere('subject', 'like', $needle)
                    ->orWhere('uuid', 'like', $needle);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        foreach (['category', 'language'] as $field) {
            if ($request->filled($field)) {
                $query->where('variables->'.$field, $request->query($field));
            }
        }

        return $view->with([
            'records' => $query->paginate(10)->withQueryString(),
            'templateRoleOptions' => $this->roles->roleOptions(),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $this->catalog->ensureForCurrentCompany();

        $templates = CommunicationTemplate::query()
            ->forCurrentCompany()
            ->where('channel', 'email')
            ->where('status', 'active')
            ->whereIn('id', $this->roles->visibleTemplateIds($request->user()))
            ->orderBy('name')
            ->get()
            ->map(fn (CommunicationTemplate $template): array => $this->roles->safeBrowserData($template))
            ->values();

        return response()->json(['templates' => $templates]);
    }

    public function editorOptions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CommunicationTemplate::class);
        $this->catalog->ensureForCurrentCompany();

        return response()->json([
            'role_options' => $this->roles->roleOptions(),
        ]);
    }
}
