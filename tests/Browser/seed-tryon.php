<?php
// Disposable CI database/storage only; never invoke this script on production.
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "This fixture is restricted to GitHub Actions testing.\n");
    exit(78);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Company,Product,TryOnAsset,TryOnVisit};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$companyId = Company::query()->where('code','ERL')->value('id') ?? Company::query()->value('id');
throw_if(!$companyId, \RuntimeException::class, 'No seeded company is available for the browser fixture.');

$product = Product::updateOrCreate(['sku'=>'BROWSER-TRYON-001'],[
    'company_id'=>$companyId,
    'name'=>'Browser Try-On Cap',
    'slug'=>'browser-tryon-cap',
    'price'=>34.99,
    'stock'=>12,
    'description'=>'Disposable virtual try-on browser fixture.',
    'is_active'=>true,
]);

$uuid=(string) Str::uuid();
$directory='tryons/'.$uuid.'/tryon1234567';
$image=imagecreatetruecolor(420,220);
imagealphablending($image,false);
imagesavealpha($image,true);
$transparent=imagecolorallocatealpha($image,0,0,0,127);
imagefill($image,0,0,$transparent);
$green=imagecolorallocatealpha($image,5,84,45,8);
$dark=imagecolorallocatealpha($image,3,48,25,0);
imagefilledellipse($image,210,105,300,135,$green);
imagefilledrectangle($image,78,98,342,158,$green);
imagefilledellipse($image,280,160,210,42,$dark);
ob_start();imagepng($image,null,7);$encoded=ob_get_clean();imagedestroy($image);
$path=$directory.'/overlay.png';
Storage::disk('local')->put($path,$encoded);

$asset=TryOnAsset::create([
    'uuid'=>$uuid,
    'product_id'=>$product->id,
    'title'=>'Browser Try-On Cap Asset',
    'type'=>'ar_ai',
    'target'=>'unisex',
    'age_range'=>'All Ages',
    'status'=>'published',
    'visibility'=>'public',
    'files'=>['preview'=>$path],
    'settings'=>TryOnAsset::DEFAULTS,
    'seo'=>['alt'=>'Browser Try-On Cap virtual try-on','title'=>'Browser Try-On Cap — Try On','tags'=>['try-on','cap']],
    'bytes'=>Storage::disk('local')->size($path),
    'updated_by'=>'CI Administrator',
]);

TryOnVisit::insert([
    ['try_on_asset_id'=>$asset->id,'visitor_hash'=>hash('sha256','tryon-a'),'day'=>now()->toDateString(),'device'=>'desktop_web','converted'=>true,'session_seconds'=>72,'created_at'=>now(),'updated_at'=>now()],
    ['try_on_asset_id'=>$asset->id,'visitor_hash'=>hash('sha256','tryon-b'),'day'=>now()->toDateString(),'device'=>'mobile_ar','converted'=>false,'session_seconds'=>38,'created_at'=>now(),'updated_at'=>now()],
]);

echo $asset->uuid."\n";
