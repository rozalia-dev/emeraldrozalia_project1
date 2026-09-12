<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommunicationTemplateIndexRequest;
use App\Http\Requests\CommunicationTemplateRequest;
use App\Http\Resources\CommunicationTemplateResource;
use App\Models\CommunicationTemplate;
use App\Services\CommunicationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CommunicationTemplateController extends Controller
{
    public function __construct(private readonly CommunicationTemplateService $service)
    {
    }

    public function store(CommunicationTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', CommunicationTemplate::class);
        $this->service->create($request->templatePayload(), $this->idempotencyKey($request));

        return back()->with('success', 'Template created.');
    }

    public function update(CommunicationTemplateRequest $request, CommunicationTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $template);
        $this->service->update($template, $request->templatePayload());

        return back()->with('success', 'Template updated.');
    }

    public function destroy(CommunicationTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);
        $this->service->delete($template);

        return back()->with('success', 'Template deleted.');
    }

    public function action(Request $request, CommunicationTemplate $template, string $action): RedirectResponse
    {
        Gate::authorize('update', $template);

        if ($action === 'duplicate') {
            $this->service->duplicate($template);
        } else {
            $this->service->transition($template, $action);
        }

        return back()->with('success', 'Template action completed.');
    }

    public function apiIndex(CommunicationTemplateIndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CommunicationTemplate::class);
        $query = CommunicationTemplate::query()
            ->forCurrentCompany()
            ->with(['creator', 'updater'])
            ->where('channel', 'email')
            ->latest('updated_at')
            ->latest('id');

        $this->applyFilters($query, $request);
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        return CommunicationTemplateResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function apiShow(CommunicationTemplate $template): CommunicationTemplateResource
    {
        Gate::authorize('view', $template);

        return new CommunicationTemplateResource($template->load(['creator', 'updater']));
    }

    public function apiStore(CommunicationTemplateRequest $request): JsonResponse
    {
        Gate::authorize('create', CommunicationTemplate::class);
        $template = $this->service->create($request->templatePayload(), $this->idempotencyKey($request));
        $template->load(['creator', 'updater']);

        return (new CommunicationTemplateResource($template))
            ->response($request)
            ->setStatusCode($template->wasRecentlyCreated ? 201 : 200);
    }

    public function apiUpdate(CommunicationTemplateRequest $request, CommunicationTemplate $template): JsonResponse
    {
        Gate::authorize('update', $template);
        $updated = $this->service->update($template, $request->templatePayload());
        $updated->load(['creator', 'updater']);

        return (new CommunicationTemplateResource($updated))->response($request);
    }

    public function apiDelete(CommunicationTemplate $template): JsonResponse
    {
        Gate::authorize('delete', $template);
        $this->service->delete($template);

        return response()->json(null, 204);
    }

    public function apiAction(Request $request, CommunicationTemplate $template, string $action): JsonResponse
    {
        Gate::authorize('update', $template);
        $result = $action === 'duplicate'
            ? $this->service->duplicate($template)
            : $this->service->transition($template, $action);
        $result->load(['creator', 'updater']);

        return (new CommunicationTemplateResource($result))->response($request);
    }

    private function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function ($templates) use ($needle): void {
                $templates->where('name', 'like', $needle)
                    ->orWhere('subject', 'like', $needle)
                    ->orWhere('uuid', 'like', $needle);
            });
        }

        foreach (['status', 'category', 'language'] as $field) {
            if (! $request->filled($field)) {
                continue;
            }

            if ($field === 'status') {
                $query->where('status', $request->input($field));
            } else {
                $query->where('variables->'.$field, $request->input($field));
            }
        }
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
