<?php
namespace App\Http\Controllers;

use App\Models\{ProductSpin,SpinVisit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpinViewerController extends Controller
{
    private function authorizeSpin(ProductSpin $spin):void { abort_unless(auth()->user()?->is_admin || $spin->isPublic(),404); }
    public function show(ProductSpin $spin)
    {
        $this->authorizeSpin($spin);
        return view('site.spin',compact('spin'));
    }
    public function frame(ProductSpin $spin,int $frame)
    {
        $this->authorizeSpin($spin);
        $path=$spin->frames[$frame]??null;
        abort_unless(is_string($path)&&preg_match('~^spins/[a-f0-9-]+/[0-9]{3}\.jpg$~',$path)&&Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Content-Type'=>'image/jpeg','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function visit(Request $request,ProductSpin $spin)
    {
        abort_unless($spin->isPublic(),404);
        $data=$request->validate(['engaged'=>'required|boolean','load_ms'=>'required|integer|between:0,60000']);
        if($request->user()?->is_admin)return response()->noContent();
        $hash=hash_hmac('sha256',$request->session()->getId(),config('app.key'));
        $key=['product_spin_id'=>$spin->id,'visitor_hash'=>$hash,'day'=>now()->toDateString()];
        SpinVisit::insertOrIgnore($key+['engaged'=>$data['engaged'],'load_ms'=>$data['load_ms']]);
        if($data['engaged'])SpinVisit::where($key)->update(['engaged'=>true]);
        return response()->noContent();
    }
    public function sitemap()
    {
        $xml='<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach(ProductSpin::with('product')->where('status','published')->where('visibility','public')->cursor() as $spin){
            if($spin->isPublic())$xml.='<url><loc>'.htmlspecialchars(route('spins.show',$spin->uuid),ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>';
        }
        return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }
}
