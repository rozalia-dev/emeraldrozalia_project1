<?php
// Disposable CI database only; never invoke this script on production.
if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "This fixture is restricted to GitHub Actions testing.\n");
    exit(78);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\App\Models\Product::updateOrCreate(['sku'=>'BROWSER-VIDEO-001'],[
    'name'=>'Browser Video Cap','slug'=>'browser-video-cap','price'=>35,'stock'=>5,
    'description'=>'Disposable video browser fixture.','is_active'=>true,
]);
