<?php
namespace Tests\Feature;

use App\Models\{Product,ProductSpin,SpinVisit,User};
use App\Services\SpinFrames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,DB,Storage};
use Tests\TestCase;
use ZipArchive;

class SpinDashboardTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void { parent::setUp();Storage::fake('local'); }
    private function product():Product { return Product::create(['name'=>'Emerald Spin Cap','slug'=>'spin-cap','sku'=>'SPIN-001','price'=>35,'stock'=>5,'is_active'=>true]); }
    private function admin():User { return User::factory()->create(['is_admin'=>true]); }
    private function archive(array $entries=['02.png','01.png']):UploadedFile
    {
        $path=tempnam(sys_get_temp_dir(),'spin-test-'); $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        foreach($entries as $name){$image=UploadedFile::fake()->image('frame.png',80,60);$zip->addFromString($name,file_get_contents($image->getRealPath()));}
        $zip->close();$data=file_get_contents($path);unlink($path);
        return UploadedFile::fake()->createWithContent('frames.zip',$data);
    }
    private function payload(Product $p,array $extra=[]):array
    {
        return array_replace(['title'=>'Cap 360 view','product_id'=>$p->id,'category'=>'product','status'=>'draft','visibility'=>'public','auto_rotate'=>'0','zoom'=>'1','fullscreen'=>'1','hotspots'=>'1','mobile'=>'1','lazy_load'=>'1','hotspot_data'=>'[]'],$extra);
    }
    private function spin(Product $p,array $extra=[]):ProductSpin
    {
        $stored=app(SpinFrames::class)->store($this->archive());
        return ProductSpin::create(array_replace(['product_id'=>$p->id,'title'=>'Spin Cap','category'=>'product','status'=>'published','visibility'=>'public','frames'=>$stored['frames'],'bytes'=>$stored['bytes'],'resolution'=>$stored['resolution'],'settings'=>ProductSpin::DEFAULTS,'seo'=>[],'hotspots'=>[]],$extra));
    }
    public function test_admin_access_and_dedicated_empty_dashboard():void
    {
        $this->get('/admin/resource/360-product-view')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/resource/360-product-view')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/resource/360-product-view')->assertOk()->assertSee(['Your 360° library starts here','UUID Traceability','Create 360° View'])->assertDontSee('Add Record');
    }
    public function test_zip_upload_persists_optimized_frames_settings_and_audit():void
    {
        $p=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/360-product-view',$this->payload($p,['archive'=>$this->archive()]))->assertRedirect();
        $spin=ProductSpin::firstOrFail();$this->assertCount(2,$spin->frames);$this->assertNotEmpty($spin->uuid);$this->assertTrue($spin->settings['zoom']);
        foreach($spin->frames as $frame){Storage::disk('local')->assertExists($frame);$this->assertStringEndsWith('.jpg',$frame);}
        $this->assertDatabaseHas('audit_logs',['action'=>'spin.created','subject_id'=>(string)$spin->id]);
        $this->get('/admin/resource/360-product-view?edit='.$spin->id)->assertOk()->assertSee('Cap 360 view');
    }
    public function test_archive_path_traversal_and_wrong_files_leave_no_partial_records():void
    {
        $p=$this->product();$this->actingAs($this->admin());
        foreach([['../01.png','02.png'],['01.png','bad.php'],['01.png']] as $entries){
            $this->postJson('/admin/resource/360-product-view',$this->payload($p,['archive'=>$this->archive($entries)]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('product_spins',0);$this->assertSame([],Storage::disk('local')->allFiles('spins'));
    }
    public function test_hotspot_validation_and_upload_limit():void
    {
        $p=$this->product();$this->actingAs($this->admin());
        $this->postJson('/admin/resource/360-product-view',$this->payload($p,['archive'=>UploadedFile::fake()->create('frames.zip',20481,'application/zip')]))->assertUnprocessable();
        $this->postJson('/admin/resource/360-product-view',$this->payload($p,['archive'=>$this->archive(),'hotspot_data'=>'[{"frame":0,"x":200,"y":50,"label":"Bad"}]']))->assertUnprocessable();
        $this->assertDatabaseCount('product_spins',0);
    }
    public function test_public_access_requires_published_public_view_and_active_product():void
    {
        $p=$this->product();$spin=$this->spin($p);$url=route('spins.frame',[$spin->uuid,0]);
        $this->get($url)->assertOk()->assertHeader('Content-Type','image/jpeg');
        $this->get(route('spins.show',$spin->uuid))->assertOk()->assertSee('data-spin-widget',false);
        foreach(['draft','archived','in_progress','needs_attention'] as $state){$spin->update(['status'=>$state]);$this->get($url)->assertNotFound();}
        $spin->update(['status'=>'published','visibility'=>'private']);$this->get($url)->assertNotFound();
        $this->actingAs($this->admin())->get($url)->assertOk();Auth::logout();
        $spin->update(['visibility'=>'public']);$p->update(['is_active'=>false]);$this->get($url)->assertNotFound();
        $this->get(route('spins.frame',[$spin->uuid,99]))->assertNotFound();
    }
    public function test_product_integration_and_sitemap_exclude_private_views():void
    {
        $p=$this->product();$public=$this->spin($p);$private=$this->spin($p,['visibility'=>'private','title'=>'Private hidden spin']);
        $this->get('/product/'.$p->slug)->assertOk()
            ->assertSee(['data-product-viewer', 'data-spin-source="managed"', '/360/'.$public->uuid.'/frames/0'], false)
            ->assertDontSee('Private hidden spin');
        $this->get('/360-sitemap.xml')->assertOk()->assertSee($public->uuid)->assertDontSee($private->uuid);
    }
    public function test_updates_preserve_uuid_and_replace_files():void
    {
        $p=$this->product();$spin=$this->spin($p);$uuid=$spin->uuid;$old=$spin->frames;
        $this->actingAs($this->admin())->patch('/admin/resource/360-product-view/'.$spin->id,$this->payload($p,['title'=>'Updated spin','archive'=>$this->archive()]))->assertRedirect();
        $this->assertSame($uuid,$spin->fresh()->uuid);$this->assertNotSame($old,$spin->fresh()->frames);
        $this->get('/admin/resource/360-product-view/'.$spin->id.'/audit')->assertOk()->assertJsonPath('entries.0.action','spin.updated');
    }
    public function test_bulk_status_changes_and_export():void
    {
        $p=$this->product();$spin=$this->spin($p,['visibility'=>'private']);$this->actingAs($this->admin());
        $this->post('/admin/resource/360-product-view/bulk',['ids'=>[$spin->id],'action'=>'archived'])->assertRedirect();
        $this->assertSame('archived',$spin->fresh()->status);$this->assertSame('private',$spin->fresh()->visibility);
        $this->get('/admin/resource/360-product-view?status=archived&q=Spin')->assertOk()->assertSee($spin->title);
        $csv=$this->get('/admin/resource/360-product-view/export')->assertOk();$this->assertStringContainsString($spin->uuid,$csv->streamedContent());
        $this->post('/admin/resource/360-product-view/bulk',['ids'=>[$spin->id],'action'=>'delete'])->assertRedirect();$this->assertDatabaseMissing('product_spins',['id'=>$spin->id]);
    }
    public function test_metrics_dedupe_and_exclude_admin_preview():void
    {
        $spin=$this->spin($this->product());$url=route('spins.visit',$spin->uuid);
        $first=$this->postJson($url,['engaged'=>false,'load_ms'=>100])->assertNoContent();
        // Carry the issued encrypted session cookie, as a real browser does.
        // Laravel's synthetic requests do not retain response cookies automatically.
        $cookie=collect($first->headers->getCookies())->first(fn($c)=>$c->getName()===config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(),$cookie->getValue())->postJson($url,['engaged'=>true,'load_ms'=>200])->assertNoContent();
        $this->assertDatabaseCount('spin_visits',1);$this->assertTrue(SpinVisit::firstOrFail()->engaged);
        $this->actingAs($this->admin())->postJson($url,['engaged'=>true,'load_ms'=>10])->assertNoContent();$this->assertDatabaseCount('spin_visits',1);
    }
    public function test_cross_company_records_cannot_be_edited_or_read():void
    {
        $p=$this->product();$spin=$this->spin($p);
        // HTTP tenant scope must hide a product whose company differs from the active session.
        $this->withSession(['company_id'=>999999])->actingAs($this->admin())->get('/admin/resource/360-product-view/'.$spin->id.'/audit')->assertNotFound();
    }
}
