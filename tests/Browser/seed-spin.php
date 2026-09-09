<?php
// Disposable CI database/storage only; never invoke this script on production.
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "This fixture is restricted to GitHub Actions testing.\n");
    exit(78);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Company,Product,ProductSpin,SpinVisit};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$companyId = Company::query()->where('code','ERL')->value('id') ?? Company::query()->value('id');
throw_if(!$companyId, \RuntimeException::class, 'No seeded company is available for the browser fixture.');

$product = Product::updateOrCreate(['sku'=>'BROWSER-SPIN-001'],[
    'company_id'=>$companyId,
    'name'=>'Browser Spin Cap',
    'slug'=>'browser-spin-cap',
    'price'=>35,
    'stock'=>5,
    'description'=>'Disposable 360 browser fixture.',
    'is_active'=>true,
]);

$uuid=(string) Str::uuid();
$directory='spins/'.$uuid;
$frames=[];
foreach ([0,1] as $index) {
    $image=imagecreatetruecolor(640,640);
    $white=imagecolorallocate($image,248,248,246);
    $green=imagecolorallocate($image,0,91,46);
    $dark=imagecolorallocate($image,8,35,22);
    imagefill($image,0,0,$white);
    imagefilledellipse($image,320,315,390,250,$green);
    imagefilledrectangle($image,205,315,435,425,$green);
    imagefilledellipse($image,$index===0?410:230,410,250,65,$dark);
    ob_start(); imagejpeg($image,null,88); $encoded=ob_get_clean(); imagedestroy($image);
    $path=$directory.'/'.sprintf('%03d',$index).'.jpg';
    Storage::disk('local')->put($path,$encoded);
    $frames[]=$path;
}

$spin=ProductSpin::create([
    'uuid'=>$uuid,
    'product_id'=>$product->id,
    'title'=>'Browser Spin Cap 360°',
    'category'=>'product',
    'status'=>'published',
    'visibility'=>'public',
    'frames'=>$frames,
    'settings'=>ProductSpin::DEFAULTS,
    'seo'=>['alt'=>'Browser Spin Cap 360 degree view','title'=>'Browser Spin Cap 360°','aria'=>'360 degree view of Browser Spin Cap'],
    'hotspots'=>[['frame'=>0,'x'=>50,'y'=>42,'label'=>'Front embroidered logo']],
    'bytes'=>array_sum(array_map(fn($path)=>Storage::disk('local')->size($path),$frames)),
    'resolution'=>'640 × 640',
    'updated_by'=>'CI Administrator',
]);

SpinVisit::insert([
    ['product_spin_id'=>$spin->id,'visitor_hash'=>hash('sha256','browser-a'),'day'=>now()->toDateString(),'engaged'=>true,'load_ms'=>190],
    ['product_spin_id'=>$spin->id,'visitor_hash'=>hash('sha256','browser-b'),'day'=>now()->toDateString(),'engaged'=>false,'load_ms'=>230],
]);

echo $spin->uuid."\n";
