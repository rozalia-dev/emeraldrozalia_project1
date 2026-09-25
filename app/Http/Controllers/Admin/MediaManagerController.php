<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product,ProductMedia,ProductSpin,ProductVideo,Review,TryOnAsset};
use App\Rules\MediaDimensions;
use App\Services\{AuditTrail, PublicMediaDerivativeService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MediaManagerController extends Controller
{
    private const TYPES = ['image','video','spin_360','try_on','document'];

    public function index(Request $request)
    {
        $products = Product::where('is_active',true)->withCount('media')->orderBy('name')->get();
        $selected = $products->firstWhere('id',(int) $request->input('product_id')) ?: $products->first();
        $mediaType = $request->string('media_type')->toString();
        $mediaStatus = $request->string('media_status')->toString();
        $mediaSort = $request->string('media_sort')->toString() ?: 'newest';
        $mediaQuery = $selected
            ? ProductMedia::where('product_id',$selected->id)
            : ProductMedia::query()->whereKey(0);

        if (in_array($mediaType, self::TYPES, true)) {
            $mediaQuery->where('type',$mediaType);
        }

        if ($mediaStatus === 'active') {
            $mediaQuery->where('active',true);
        } elseif ($mediaStatus === 'inactive') {
            $mediaQuery->where('active',false);
        }

        $media = match ($mediaSort) {
            'oldest' => $mediaQuery->orderBy('created_at')->orderBy('id')->get(),
            'order' => $mediaQuery->orderBy('sort_order')->orderBy('id')->get(),
            default => $mediaQuery->orderByDesc('created_at')->orderBy('id')->get(),
        };

        if ($mediaStatus === 'archived') {
            $media = $media->filter(fn (ProductMedia $item): bool => data_get($item->metadata,'status') === 'archived')->values();
        }

        if ($mediaType === 'document') {
            $media = $media->where('type','document')->values();
        }

        $mediaReadiness = null;
        if ($selected) {
            $publicImages = ProductMedia::query()
                ->where('product_id', $selected->id)
                ->whereIn('type', ['image', 'gallery'])
                ->where('approval_status', 'approved')
                ->where('active', true)
                ->count();

            $genericSpinFrames = ProductMedia::query()
                ->where('product_id', $selected->id)
                ->where('type', 'spin_360')
                ->where('approval_status', 'approved')
                ->where('active', true)
                ->count();

            $publicSpin = ProductSpin::query()
                ->with('product')
                ->where('product_id', $selected->id)
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at')
                ->get()
                ->first(fn (ProductSpin $spin): bool => $spin->isPublic());

            $publicVideos = ProductVideo::query()
                ->with('product')
                ->where('product_id', $selected->id)
                ->where('approval_status', 'approved')
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->filter(fn (ProductVideo $video): bool => $video->isPubliclyPlayable() && (bool) data_get($video->metadata, 'gallery', true))
                ->count();

            $publicTryOnAsset = TryOnAsset::query()
                ->with('product')
                ->where('product_id', $selected->id)
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at')
                ->get()
                ->first(fn (TryOnAsset $asset): bool => $asset->isPublic());

            $genericTryOn = ProductMedia::query()
                ->where('product_id', $selected->id)
                ->where('type', 'try_on')
                ->where('approval_status', 'approved')
                ->where('active', true)
                ->count();

            $approvedReviews = Review::query()
                ->approved()
                ->where('product_id', $selected->id)
                ->count();

            $mediaReadiness = [
                'images' => [
                    'ready' => $publicImages > 0,
                    'count' => min(6, $publicImages),
                    'detail' => min(6, $publicImages).' of 6 public colour/product images',
                ],
                'spin' => [
                    'ready' => $publicSpin !== null || $genericSpinFrames >= 2,
                    'count' => $publicSpin ? count($publicSpin->frames ?? []) : $genericSpinFrames,
                    'detail' => $publicSpin
                        ? count($publicSpin->frames ?? []).' published 360° frames'
                        : ($genericSpinFrames >= 2 ? $genericSpinFrames.' approved 360° frames' : 'Publish a 360° ZIP/frame set'),
                ],
                'video' => [
                    'ready' => $publicVideos > 0,
                    'count' => $publicVideos,
                    'detail' => $publicVideos > 0 ? $publicVideos.' public gallery video'.($publicVideos === 1 ? '' : 's') : 'Publish a public gallery video',
                ],
                'tryon' => [
                    'ready' => $publicTryOnAsset !== null || $genericTryOn > 0,
                    'count' => ($publicTryOnAsset ? 1 : 0) + $genericTryOn,
                    'detail' => $publicTryOnAsset || $genericTryOn > 0 ? 'Public Try-On asset ready' : 'Publish a public Try-On preview',
                ],
                'reviews' => [
                    'ready' => $approvedReviews > 0,
                    'count' => $approvedReviews,
                    'detail' => $approvedReviews.' approved customer review'.($approvedReviews === 1 ? '' : 's'),
                ],
            ];
        }

        return view('admin.media-manager.index',compact('products','selected','media','mediaType','mediaStatus','mediaSort','mediaReadiness'));
    }

    public function store(Request $request, PublicMediaDerivativeService $derivatives)
    {
        $data = $request->validate([
            'product_id'=>'required|integer|exists:products,id',
            'type'=>['required',Rule::in(self::TYPES)],
            'disk'=>['required',Rule::in(['public','local'])],
            'path'=>'nullable|string|max:255',
            'file'=>['nullable', 'file', new MediaDimensions, 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,image/avif,video/mp4,video/webm,video/quicktime,model/gltf-binary,model/gltf+json,application/pdf'],
            'alt_text'=>'nullable|string|max:255',
            'sort_order'=>'nullable|integer|min:0|max:10000',
            'active'=>'nullable|boolean',
        ]);
        abort_unless(Product::whereKey($data['product_id'])->where('is_active',true)->exists(),404);
        if ($request->hasFile('file')) {
            $data['path']=$request->file('file')->store('product-media/'.$data['product_id'],$data['disk']);
        }
        abort_unless(filled($data['path']) && Storage::disk($data['disk'])->exists($data['path']),422,'Provide an existing media path or upload a file.');
        unset($data['file']);
        $file = $request->file('file');
        $dimensions = $file ? @getimagesize($file->getRealPath()) : false;
        $media=ProductMedia::create($data+[
            'active'=>(bool)($data['active']??true),
            'approval_status'=>'pending',
            'mime_type'=>$file?->getMimeType(),
            'width'=>(int)($dimensions[0] ?? 0) ?: null,
            'height'=>(int)($dimensions[1] ?? 0) ?: null,
            'bytes'=>(int)($file?->getSize() ?: 0) ?: null,
        ]);
        $media->updateQuietly(['responsive_variants' => $derivatives->generate($media, 1) ?: null]);
        AuditTrail::record('media.created',$media,null,$media->toArray());
        return redirect()->route('admin.media.index',['product_id'=>$media->product_id])->with('success','Media added.');
    }

    public function update(Request $request,ProductMedia $media)
    {
        $data = $request->validate(['type'=>['required',Rule::in(self::TYPES)],'alt_text'=>'nullable|string|max:255','sort_order'=>'required|integer|min:0|max:10000','active'=>'nullable|boolean']);
        $before=$media->toArray(); $media->update($data+['active'=>(bool)($data['active']??false)]); AuditTrail::record('media.updated',$media,$before,$media->fresh()->toArray());
        return redirect()->route('admin.media.index',['product_id'=>$media->product_id])->with('success','Media updated.');
    }

    public function destroy(ProductMedia $media)
    {
        $productId=$media->product_id; $product=$media->product; $before=$media->toArray(); AuditTrail::record('media.deleted',$media,$before,null); $media->delete();
        $product?->syncPrimaryImageFromMedia();
        return redirect()->route('admin.media.index',['product_id'=>$productId])->with('success','Media removed.');
    }

    public function approve(ProductMedia $media)
    {
        $before = $media->toArray();
        $media->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'active' => true,
        ]);
        AuditTrail::record('media.approved', $media, $before, $media->fresh()->toArray());
        $media->product?->syncPrimaryImageFromMedia();

        return back()->with('success', 'Product media approved for public delivery.');
    }

    public function reject(ProductMedia $media)
    {
        $before = $media->toArray();
        $media->update([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'approved_by' => null,
            'active' => false,
        ]);
        AuditTrail::record('media.rejected', $media, $before, $media->fresh()->toArray());
        $media->product?->syncPrimaryImageFromMedia();

        return back()->with('success', 'Product media rejected and removed from public delivery.');
    }
}
