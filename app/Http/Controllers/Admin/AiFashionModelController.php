<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Validation\Rule;

class AiFashionModelController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::orderBy('name')->get(['id','name','sku','image']);
        $selected = $request->integer('product_id') ? $products->firstWhere('id',$request->integer('product_id')) : null;
        return view('admin.ai-fashion-model.index', compact('products','selected'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'product_id'=>'required|integer|exists:products,id',
            'model'=>['required',Rule::in(['female','male','unisex','kids'])],
            'pose'=>['required',Rule::in(['front','left','right','back','full_body','portrait'])],
            'scene'=>['required',Rule::in(['studio','runway','irish_heritage','outdoor','luxury'])],
            'notes'=>'nullable|string|max:800',
        ]);
        $product = Product::with('previewMedia')->findOrFail($data['product_id']);
        $source = $product->previewMedia->first()?->path ?: $product->getRawOriginal('image');
        abort_unless($source, 422, 'This product needs an approved reference image before AI Fashion Model generation.');

        $provider = config('services.fashion_ai');
        abort_unless(($provider['key'] ?? null) && ($provider['url'] ?? null), 503,
            'AI Fashion Model provider is not configured yet. Add FASHION_AI_API_KEY and FASHION_AI_API_URL to production.');

        $prompt = 'Create premium ecommerce fashion photography. The model must wear the exact supplied product. Preserve product shape, colour, embroidery, logo, branding and construction details. Do not invent or replace branding. Model: '.$data['model'].'. Pose: '.$data['pose'].'. Scene: '.$data['scene'].'. '.($data['notes'] ?? '');
        $image = Storage::disk('public')->exists($source) ? Storage::disk('public')->get($source) : (Storage::disk('local')->exists($source) ? Storage::disk('local')->get($source) : null);
        abort_unless($image, 422, 'The selected product reference image could not be read.');

        $response = Http::withToken($provider['key'])->timeout(90)->attach('product_image',$image,basename($source))
            ->post($provider['url'], ['prompt'=>$prompt,'product_id'=>(string)$product->id,'sku'=>(string)$product->sku]);
        $response->throw();

        return response()->json([
            'status'=>'submitted',
            'message'=>'AI Fashion Model generation submitted for '.$product->name.'.',
            'provider_response'=>$response->json(),
        ], 202);
    }
}
