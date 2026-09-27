<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiFashionGeneration;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiFashionModelController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::orderBy('name')->get(['id','name','sku','image']);
        $selected = $request->integer('product_id') ? $products->firstWhere('id',$request->integer('product_id')) : null;
        $generations = AiFashionGeneration::with(['product','media'])->latest()->limit(24)->get();

        return view('admin.ai-fashion-model.index', compact('products','selected','generations'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'product_id'=>'required|integer|exists:products,id',
            'model'=>['required',Rule::in(['female','male','unisex','kids'])],
            'pose'=>['required',Rule::in(['front','left','right','back','rear_three_quarter','full_body','portrait','walking'])],
            'scene'=>['required',Rule::in(['studio','runway','irish_heritage','outdoor','luxury'])],
            'notes'=>'nullable|string|max:800',
        ]);

        $product = Product::with('previewMedia')->findOrFail($data['product_id']);

        $pending = AiFashionGeneration::where('product_id', $product->id)
            ->whereIn('status', ['starting','in_queue','processing'])
            ->latest()
            ->first();
        if ($pending) {
            $payload = [
                'id'=>$pending->id,
                'prediction_id'=>$pending->prediction_id,
                'status'=>$pending->status,
                'status_url'=>route('admin.ai-fashion-model.status',$pending),
                'message'=>'A FASHN generation is already pending for '.$product->name.'. Continuing that job instead of charging for a duplicate.',
            ];
            if ($request->expectsJson()) {
                return response()->json($payload, 200);
            }
            return redirect()->route('admin.ai-fashion-model.index')
                ->with('status', $payload['message']);
        }

        $source = $product->previewMedia->first()?->path ?: $product->getRawOriginal('image');
        abort_unless($source, 422, 'This product needs an approved reference image before AI Fashion Model generation.');

        $provider = config('services.fashion_ai');
        abort_unless(($provider['key'] ?? null) && ($provider['url'] ?? null), 503, 'FASHN is not configured.');

        [$image,$mime] = $this->readSourceImage($source);
        abort_unless($image, 422, 'The selected product reference image could not be read.');

        $prompt = $this->prompt($data);
        $response = Http::withToken($provider['key'])->acceptJson()->asJson()->timeout(90)->post($provider['url'], [
            'model_name'=>'product-to-model',
            'inputs'=>[
                'product_image'=>'data:'.$mime.';base64,'.base64_encode($image),
                'prompt'=>$prompt,
                'aspect_ratio'=>'3:4',
                'resolution'=>'1k',
                'generation_mode'=>'quality',
                'num_images'=>1,
                'output_format'=>'png',
                'return_base64'=>false,
            ],
        ]);
        $response->throw();

        $predictionId = $response->json('id');
        abort_unless(is_string($predictionId) && $predictionId !== '', 502, 'FASHN did not return a prediction ID.');

        $generation = AiFashionGeneration::create([
            'product_id'=>$product->id,
            'provider'=>'fashn',
            'prediction_id'=>$predictionId,
            'status'=>'starting',
            'model'=>$data['model'],
            'pose'=>$data['pose'],
            'scene'=>$data['scene'],
            'prompt'=>$prompt,
            'source_path'=>$source,
            'provider_payload'=>$response->json(),
        ]);

        $payload = [
            'id'=>$generation->id,
            'prediction_id'=>$predictionId,
            'status'=>'starting',
            'status_url'=>route('admin.ai-fashion-model.status',$generation),
            'message'=>'FASHN generation started for '.$product->name.'.',
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 202);
        }

        return redirect()->route('admin.ai-fashion-model.index')
            ->with('status', $payload['message'].' Use CHECK STATUS below while FASHN processes it.');
    }

    public function status(Request $request, AiFashionGeneration $generation)
    {
        if (in_array($generation->status, ['completed','approved','published','failed'], true)) {
            return $this->statusResponse($request, $generation);
        }

        $provider = config('services.fashion_ai');
        abort_unless(($provider['key'] ?? null) && ($provider['url'] ?? null), 503, 'FASHN is not configured.');

        $response = Http::withToken($provider['key'])->acceptJson()->timeout(45)
            ->get($this->statusUrl($provider['url'], $generation->prediction_id));
        $response->throw();

        $payload = $response->json();
        $status = (string) ($payload['status'] ?? 'processing');

        if ($status === 'completed') {
            $output = data_get($payload, 'output.0');
            if (!is_string($output) || $output === '') {
                $generation->update(['status'=>'failed','provider_error'=>'FASHN completed without an output image.','provider_payload'=>$payload]);
                return $this->statusResponse($request, $generation->fresh(), 502);
            }
            $download = Http::timeout(90)->get($output);
            $download->throw();
            $path = 'ai-fashion-model/'.$generation->product_id.'/'.$generation->prediction_id.'.png';
            Storage::disk('public')->put($path, $download->body());
            $generation->update([
                'status'=>'completed',
                'result_disk'=>'public',
                'result_path'=>$path,
                'provider_payload'=>$payload,
                'completed_at'=>now(),
            ]);
        } elseif ($status === 'failed') {
            $generation->update([
                'status'=>'failed',
                'provider_error'=>is_string($payload['error'] ?? null) ? $payload['error'] : json_encode($payload['error'] ?? 'FASHN generation failed.'),
                'provider_payload'=>$payload,
            ]);
        } else {
            $generation->update(['status'=>$status,'provider_payload'=>$payload]);
        }

        return $this->statusResponse($request, $generation->fresh());
    }

    public function approve(AiFashionGeneration $generation)
    {
        abort_unless($generation->status === 'completed' && $generation->result_path, 422, 'Only completed generations can be approved.');
        $generation->update(['status'=>'approved','approved_at'=>now(),'approved_by'=>auth()->id()]);
        return back()->with('status','AI Fashion Model image approved. It is ready to publish.');
    }

    public function publish(AiFashionGeneration $generation)
    {
        abort_unless($generation->approved_at && $generation->result_path, 422, 'Approve this generation before publishing.');
        if ($generation->product_media_id) {
            return back()->with('status','This AI Fashion Model image is already published.');
        }

        $disk = $generation->result_disk ?: 'public';
        abort_unless(Storage::disk($disk)->exists($generation->result_path), 422, 'Generated image file is missing.');

        $media = ProductMedia::create([
            'uuid'=>(string) Str::uuid(),
            'product_id'=>$generation->product_id,
            'type'=>'image',
            'disk'=>$disk,
            'path'=>$generation->result_path,
            'alt_text'=>$generation->product->name.' AI fashion model '.$generation->pose.' view',
            'sort_order'=>(int) ProductMedia::where('product_id',$generation->product_id)->max('sort_order') + 1,
            'metadata'=>[
                'source'=>'ai-fashion-model',
                'provider'=>'fashn',
                'prediction_id'=>$generation->prediction_id,
                'pose'=>$generation->pose,
                'scene'=>$generation->scene,
            ],
            'active'=>true,
            'approval_status'=>'approved',
            'approved_at'=>now(),
            'approved_by'=>auth()->id(),
            'mime_type'=>'image/png',
            'bytes'=>Storage::disk($disk)->size($generation->result_path),
        ]);

        $generation->update(['status'=>'published','published_at'=>now(),'product_media_id'=>$media->id]);
        return back()->with('status','AI Fashion Model image published to the product gallery.');
    }

    private function readSourceImage(string $source): array
    {
        foreach (['public','local'] as $disk) {
            if (Storage::disk($disk)->exists($source)) {
                $bytes = Storage::disk($disk)->get($source);
                $mime = Storage::disk($disk)->mimeType($source) ?: 'image/jpeg';
                return [$bytes,$mime];
            }
        }
        return [null,null];
    }

    private function prompt(array $data): string
    {
        $pose = str_replace('_',' ',$data['pose']);
        $scene = str_replace('_',' ',$data['scene']);
        return trim('Premium ecommerce fashion photography. A '.$data['model'].' fashion model wearing the exact supplied Emerald Rozalia product. '.$pose.' view/pose in a '.$scene.' setting. Preserve the product shape, colour, material, embroidery, logo, branding and construction details. Do not replace, redesign or invent product branding. '.($data['notes'] ?? ''));
    }

    private function statusUrl(string $runUrl, string $predictionId): string
    {
        $base = preg_replace('~/run/?$~','',rtrim($runUrl,'/'));
        return $base.'/status/'.rawurlencode($predictionId);
    }

    private function statusResponse(Request $request, AiFashionGeneration $generation, int $code = 200)
    {
        $payload = $this->statusPayload($generation);
        if ($request->expectsJson()) {
            return response()->json($payload, $code);
        }

        $message = 'FASHN status: '.strtoupper($generation->status).'.';
        if ($generation->status === 'completed') {
            $message .= ' Preview is ready below. Review it, then approve.';
        } elseif ($generation->status === 'failed') {
            $message .= ' '.($generation->provider_error ?: 'Generation failed.');
        } else {
            $message .= ' FASHN is still processing. Check again shortly.';
        }

        return redirect()->route('admin.ai-fashion-model.index')->with('status', $message);
    }

    private function statusPayload(AiFashionGeneration $generation): array
    {
        return [
            'id'=>$generation->id,
            'status'=>$generation->status,
            'error'=>$generation->provider_error,
            'preview_url'=>$generation->result_path ? Storage::disk($generation->result_disk ?: 'public')->url($generation->result_path) : null,
            'approved'=>(bool) $generation->approved_at,
            'published'=>(bool) $generation->published_at,
        ];
    }
}
