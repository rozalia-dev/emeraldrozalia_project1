<?php

namespace Tests\Feature;

use App\Models\{Product,TryOnAsset,TryOnVisit,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Storage};
use Tests\TestCase;
use ZipArchive;

class TryOnDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function product(array $extra=[]): Product
    {
        return Product::create(array_replace([
            'name'=>'Emerald Try-On Cap','slug'=>'emerald-tryon-cap','sku'=>'TRYON-001','price'=>34.99,'stock'=>10,'is_active'=>true,
        ],$extra));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin'=>true]);
    }

    private function payload(Product $product,array $extra=[]): array
    {
        return array_replace([
            'product_id'=>$product->id,'title'=>'Emerald Cap Try-On','type'=>'ar_ai','target'=>'unisex','age_range'=>'All Ages',
            'status'=>'draft','visibility'=>'public','auto_fit'=>'1','face_detection'=>'1','realistic_lighting'=>'1','shadow_rendering'=>'1',
            'occlusion'=>'1','high_quality'=>'0','mobile'=>'1','alt'=>'Emerald cap virtual try-on','seo_title'=>'Emerald Cap — Try On','tags'=>'try-on, cap, emerald',
        ],$extra);
    }

    private function overlay(): UploadedFile
    {
        return UploadedFile::fake()->image('overlay.png',240,140);
    }

    private function zip(array $entries): UploadedFile
    {
        $path=tempnam(sys_get_temp_dir(),'tryon-test-');
        $zip=new ZipArchive();
        $zip->open($path,ZipArchive::OVERWRITE);
        foreach($entries as $name=>$contents){
            if($contents==='image'){
                $image=UploadedFile::fake()->image('asset.png',120,80);
                $contents=file_get_contents($image->getRealPath());
            }
            $zip->addFromString($name,$contents);
        }
        $zip->close();
        $data=file_get_contents($path);unlink($path);
        return UploadedFile::fake()->createWithContent('try-on.zip',$data);
    }

    public function test_dedicated_dashboard_requires_admin_and_renders_mockup_sections(): void
    {
        $this->get('/admin/resource/virtual-try-on')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/resource/virtual-try-on')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/resource/virtual-try-on')->assertOk()->assertSee([
            'Virtual Try-On','Try-On Summary','Try-Ons by Device','Quick Actions','UUID Traceability','Realistic Experience','Create Try-On',
        ]);
    }

    public function test_upload_persists_optimized_preview_settings_seo_and_audit(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay()]))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();
        $this->assertNotEmpty($asset->uuid);
        $this->assertTrue($asset->settings['auto_fit']);
        $this->assertSame(['try-on','cap','emerald'],$asset->seo['tags']);
        Storage::disk('local')->assertExists($asset->previewPath());
        $this->assertStringEndsWith('.png',$asset->previewPath());
        $this->assertDatabaseHas('audit_logs',['action'=>'tryon.created','subject_id'=>(string)$asset->id]);
        $this->get('/admin/resource/virtual-try-on?edit='.$asset->id)->assertOk()->assertSee('Emerald Cap Try-On');
    }

    public function test_zip_rejects_traversal_and_executable_entries_without_partial_record(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin());
        $this->postJson('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->zip(['../overlay.png'=>'image'])]))->assertUnprocessable();
        $this->postJson('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->zip(['overlay.png'=>'image','payload.php'=>'<?php echo 1;'])]))->assertUnprocessable();
        $this->assertDatabaseCount('try_on_assets',0);
        $this->assertSame([],Storage::disk('local')->allFiles('tryons'));
    }

    public function test_model_only_asset_can_be_draft_but_cannot_be_published(): void
    {
        $product=$this->product();
        $glb='glTF'.str_repeat("\0",64);
        $draft=UploadedFile::fake()->createWithContent('model.glb',$glb);
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$draft]))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();
        $this->assertNotNull($asset->modelPath());
        $this->assertNull($asset->previewPath());

        $this->patch('/admin/resource/virtual-try-on/'.$asset->id,$this->payload($product,['status'=>'published']))->assertSessionHasErrors('asset');
        $this->assertSame('draft',$asset->fresh()->status);
    }

    public function test_public_preview_requires_published_public_asset_and_active_product(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay(),'status'=>'published']))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();
        Auth::logout();
        $this->get(route('tryons.asset',[$asset->uuid,'preview']))->assertOk()->assertHeader('Content-Type','image/png');
        $asset->update(['status'=>'draft']);
        $this->get(route('tryons.asset',[$asset->uuid,'preview']))->assertNotFound();
        $asset->update(['status'=>'published','visibility'=>'private']);
        $this->get(route('tryons.asset',[$asset->uuid,'preview']))->assertNotFound();
        $asset->update(['visibility'=>'public']);$product->update(['is_active'=>false]);
        $this->get(route('tryons.asset',[$asset->uuid,'preview']))->assertNotFound();
    }

    public function test_storefront_prefers_managed_published_asset_and_product_link_launches_studio(): void
    {
        $product=$this->product(['try_on_asset'=>'legacy/overlay.png']);
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay(),'status'=>'published']))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();
        Auth::logout();
        $response=$this->get('/virtual-tryon?product_id='.$product->id)->assertOk();
        $response->assertSee(route('tryons.asset',[$asset->uuid,'preview']),false)->assertDontSee('legacy/overlay.png',false)->assertSee('data-try-meta',false);
        $this->get('/product/'.$product->slug)->assertOk()->assertSee('/virtual-tryon?product_id='.$product->id,false);
    }

    public function test_update_preserves_uuid_and_replaces_files_after_success(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay()]))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();$uuid=$asset->uuid;$old=$asset->files;
        $this->patch('/admin/resource/virtual-try-on/'.$asset->id,$this->payload($product,['title'=>'Updated Try-On','asset'=>$this->overlay()]))->assertRedirect();
        $fresh=$asset->fresh();
        $this->assertSame($uuid,$fresh->uuid);$this->assertNotSame($old,$fresh->files);
        foreach($old as $path)Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertExists($fresh->previewPath());
        $this->get('/admin/resource/virtual-try-on/'.$asset->id.'/audit')->assertOk()->assertJsonPath('entries.0.action','tryon.updated');
    }

    public function test_bulk_status_export_and_delete_work(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay()]))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();
        $this->post('/admin/resource/virtual-try-on/bulk',['ids'=>[$asset->id],'action'=>'in_review'])->assertRedirect();
        $this->assertSame('in_review',$asset->fresh()->status);
        $csv=$this->get('/admin/resource/virtual-try-on/export')->assertOk();
        $this->assertStringContainsString($asset->uuid,$csv->streamedContent());
        $this->post('/admin/resource/virtual-try-on/bulk',['ids'=>[$asset->id],'action'=>'delete'])->assertRedirect();
        $this->assertDatabaseMissing('try_on_assets',['id'=>$asset->id]);
        $this->assertSame([],Storage::disk('local')->allFiles('tryons/'.$asset->uuid));
    }

    public function test_visit_metrics_dedupe_and_exclude_admin_preview(): void
    {
        $product=$this->product();
        $this->actingAs($this->admin())->post('/admin/resource/virtual-try-on',$this->payload($product,['asset'=>$this->overlay(),'status'=>'published']))->assertRedirect();
        $asset=TryOnAsset::firstOrFail();Auth::logout();
        $url=route('tryons.visit',$asset->uuid);
        $first=$this->postJson($url,['device'=>'desktop_web','converted'=>false,'session_seconds'=>4])->assertNoContent();
        $cookie=collect($first->headers->getCookies())->first(fn($cookie)=>$cookie->getName()===config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(),$cookie->getValue())->postJson($url,['device'=>'desktop_web','converted'=>true,'session_seconds'=>15])->assertNoContent();
        $this->assertDatabaseCount('try_on_visits',1);
        $visit=TryOnVisit::firstOrFail();$this->assertTrue($visit->converted);$this->assertSame(15,$visit->session_seconds);
        $this->actingAs($this->admin())->postJson($url,['device'=>'desktop_web','converted'=>true,'session_seconds'=>30])->assertNoContent();
        $this->assertDatabaseCount('try_on_visits',1);
    }
}
