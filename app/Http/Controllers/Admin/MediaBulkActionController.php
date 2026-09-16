<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{MediaAsset, ProductMedia};
use App\Services\{AuditTrail, MediaAssetUsageService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MediaBulkActionController extends Controller
{
    public function productMedia(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'approve', 'reject', 'delete'])],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($data, $ids, $action): void {
            $items = ProductMedia::query()
                ->where('product_id', $data['product_id'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            abort_unless($items->count() === count($ids), 422, 'One or more selected media items are unavailable for this product.');

            foreach ($items as $item) {
                $before = $item->toArray();

                if ($action === 'delete') {
                    AuditTrail::record('media.bulk_deleted', $item, $before, null);
                    $item->delete();
                    continue;
                }

                $attributes = match ($action) {
                    'activate' => ['active' => true],
                    'deactivate' => ['active' => false],
                    'approve' => [
                        'approval_status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                        'active' => true,
                    ],
                    'reject' => [
                        'approval_status' => 'rejected',
                        'approved_at' => null,
                        'approved_by' => null,
                        'active' => false,
                    ],
                };

                $item->update($attributes);
                AuditTrail::record('media.bulk_'.$action, $item, $before, $item->fresh()->toArray());
            }
        });

        return back()->with('success', 'Selected product media updated.');
    }

    public function siteMedia(Request $request, MediaAssetUsageService $usage): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['approve', 'reject', 'archive', 'trash', 'restore', 'permanent_delete'])],
        ]);

        $ids = array_values(array_unique($data['ids']));
        $action = $data['action'];

        $query = MediaAsset::withTrashed()->visibleToCurrentCompany()->whereIn('uuid', $ids);
        if (in_array($action, ['restore', 'permanent_delete'], true)) {
            $query->whereNotNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
        }

        $assets = $query->get();
        abort_unless($assets->count() === count($ids), 422, 'One or more selected media assets are unavailable in the current company or view.');

        if ($action === 'permanent_delete') {
            $blocked = $assets->filter(fn (MediaAsset $asset): bool => $usage->for($asset) !== []);
            if ($blocked->isNotEmpty()) {
                return back()->withErrors([
                    'media' => 'Permanent deletion stopped: '.count($blocked).' selected asset(s) are still referenced. Remove those references first.',
                ]);
            }
        }

        $controller = app(MediaAssetController::class);
        foreach ($assets as $asset) {
            match ($action) {
                'approve' => $controller->approve($asset),
                'reject' => $controller->reject($asset),
                'archive' => $controller->archive($asset->uuid),
                'trash' => $controller->destroy($asset),
                'restore' => $controller->restore($asset->uuid),
                'permanent_delete' => $controller->permanentDestroy($asset->uuid, $usage),
            };
        }

        return back()->with('success', 'Selected site media updated.');
    }
}
