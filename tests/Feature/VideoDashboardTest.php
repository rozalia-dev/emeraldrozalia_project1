<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Product, ProductMedia, ProductVideo, User, VideoPlay};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Storage};
use Tests\TestCase;

class VideoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function product(): Product
    {
        return Product::create(['name'=>'Emerald Video Cap','slug'=>'emerald-video-cap','sku'=>'VIDEO-CAP-001','price'=>35,'stock'=>5,'is_active'=>true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin'=>true]);
    }

    private function payload(Product $product, array $overrides = []): array
    {
        return array_replace([
            'title'=>'Signature Cap Overview','product_id'=>$product->id,'category'=>'product',
            'platform'=>'Website','status'=>'draft','visibility'=>'public','gallery'=>'1',
            'allow_download'=>'0','description'=>'An overview of our signature cap.',
            'seo_title'=>'Signature Cap Video','tags'=>'cap, emerald','caption_language'=>'en',
        ],$overrides);
    }

    private function video(Product $product, array $metadata = [], bool $active = true): ProductVideo
    {
        $path = 'videos/files/'.fake()->uuid().'.mp4';
        Storage::disk('local')->put($path,'test-video-content');
        return ProductVideo::create([
            'product_id'=>$product->id,'disk'=>'local','path'=>$path,'active'=>$active,
            'metadata'=>array_replace(['title'=>'Signature Cap Overview','category'=>'product','platform'=>'Website','visibility'=>'public','gallery'=>true,'bytes'=>18],$metadata),
        ]);
    }

    public function test_dashboard_requires_an_admin_and_uses_the_dedicated_media_workflow(): void
    {
        $this->get('/admin/resource/videos')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->get('/admin/resource/videos')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/resource/videos')->assertOk()
            ->assertSee(['Videos','Your video library starts here','Video Summary','UUID Traceability','SEO &amp; Accessibility','/js/videos.js','/css/videos.css'],false)
            ->assertDontSee('Add Record',false);
        $this->actingAs(User::factory()->create(['is_admin'=>false]))->postJson('/admin/resource/videos',[])->assertForbidden();
    }

    public function test_upload_persists_private_files_metadata_captions_uuid_and_audit(): void
    {
        $product = $this->product();
        $response = $this->actingAs($this->admin())->postJson('/admin/resource/videos',$this->payload($product,[
            'file'=>UploadedFile::fake()->create('cap.mp4',20,'video/mp4'),
            'poster'=>UploadedFile::fake()->image('poster.jpg',320,180),
            'captions'=>UploadedFile::fake()->createWithContent('captions.vtt',"WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nEmerald cap\n"),
            'duration'=>2,'resolution'=>'320 x 180',
        ]))->assertCreated();
        $video = ProductVideo::firstOrFail();
        $this->assertSame('video',$video->type);
        $this->assertSame('local',$video->disk);
        $this->assertSame('draft',$video->video_status);
        $this->assertNotEmpty($video->uuid);
        Storage::disk('local')->assertExists($video->path);
        Storage::disk('local')->assertExists($video->metadata['poster']);
        Storage::disk('local')->assertExists($video->metadata['captions']);
        $this->assertDatabaseHas('audit_logs',['action'=>'video.created','subject_id'=>(string)$video->id]);
        $response->assertJsonPath('video.title','Signature Cap Overview');
        $this->get('/admin/resource/media-manager?product_id='.$product->id)->assertOk()->assertSee('Signature Cap Overview');
    }

    public function test_invalid_uploads_links_captions_and_schedules_are_rejected_without_partial_records(): void
    {
        $product = $this->product(); $this->actingAs($this->admin());
        $this->postJson('/admin/resource/videos',$this->payload($product))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/admin/resource/videos',$this->payload($product,['file'=>UploadedFile::fake()->create('large.mp4',20481,'video/mp4')]))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/admin/resource/videos',$this->payload($product,['file'=>UploadedFile::fake()->create('bad.html',1,'text/html')]))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/admin/resource/videos',$this->payload($product,['platform'=>'YouTube','external_url'=>'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ']))->assertUnprocessable()->assertJsonValidationErrors('external_url');
        $this->postJson('/admin/resource/videos',$this->payload($product,['platform'=>'YouTube','external_url'=>'https://youtu.be/dQw4w9WgXcQ','captions'=>UploadedFile::fake()->createWithContent('bad.vtt','<script>alert(1)</script>')]))->assertUnprocessable()->assertJsonValidationErrors('captions');
        $this->postJson('/admin/resource/videos',$this->payload($product,['platform'=>'Vimeo','external_url'=>'https://vimeo.com/12345678','status'=>'scheduled','publish_at'=>now()->subDay()->toIso8601String()]))->assertUnprocessable()->assertJsonValidationErrors('publish_at');
        $this->assertDatabaseCount('product_media',0);
        $this->assertEmpty(Storage::disk('local')->allFiles('videos'));
    }

    public function test_youtube_and_vimeo_urls_are_normalized_without_fetching_external_content(): void
    {
        $product=$this->product(); $this->actingAs($this->admin());
        $this->postJson('/admin/resource/videos',$this->payload($product,['platform'=>'YouTube','external_url'=>'https://youtu.be/dQw4w9WgXcQ','status'=>'published']))
            ->assertCreated()->assertJsonPath('video.embed','https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
        $this->postJson('/admin/resource/videos',$this->payload($product,['platform'=>'Vimeo','external_url'=>'https://vimeo.com/12345678']))
            ->assertCreated()->assertJsonPath('video.embed','https://player.vimeo.com/video/12345678');
        $this->assertDatabaseCount('product_media',2);
        $this->assertEmpty(Storage::disk('local')->allFiles('videos'));
    }

    public function test_public_delivery_enforces_draft_schedule_privacy_download_and_product_state(): void
    {
        $product=$this->product();
        $published=$this->video($product);
        $draft=$this->video($product,[],false);
        $private=$this->video($product,['visibility'=>'private']);
        $scheduled=$this->video($product,['publish_at'=>now()->addHour()->toIso8601String()]);
        $this->get(route('videos.asset',[$published->uuid,'video']))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->get(route('videos.asset',[$published->uuid,'video']).'?download=1')->assertForbidden();
        foreach([$draft,$private,$scheduled] as $video) {
            $this->get(route('videos.asset',[$video->uuid,'video']))->assertNotFound();
            $this->get(route('videos.watch',$video->uuid))->assertNotFound();
        }
        $this->travel(2)->hours();
        $this->get(route('videos.asset',[$scheduled->uuid,'video']))->assertOk();
        $this->travelBack();
        $this->actingAs($this->admin())->get(route('videos.asset',[$private->uuid,'video']))->assertOk();
        Auth::logout();
        $product->update(['is_active'=>false]);
        $this->get(route('videos.asset',[$published->uuid,'video']))->assertNotFound();
    }

    public function test_published_gallery_and_sitemap_hide_private_draft_and_disabled_gallery_entries(): void
    {
        $product=$this->product();
        $visible=$this->video($product,['title'=>'Visible product video']);
        $this->video($product,['title'=>'Private product video','visibility'=>'private']);
        $this->video($product,['title'=>'Draft product video'],false);
        $this->video($product,['title'=>'Standalone video','gallery'=>false]);
        $this->get('/product/'.$product->slug)->assertOk()->assertSee(['Visible product video','data-er-video'],false)
            ->assertDontSee('Private product video')->assertDontSee('Draft product video')->assertDontSee('Standalone video');
        $this->get('/video-sitemap.xml')->assertOk()->assertSee($visible->uuid)->assertDontSee('Private product video');
        $this->get(route('videos.watch',$visible->uuid))->assertOk()->assertSee(['Visible product video','/js/video-playback.js'],false);
    }

    public function test_search_filters_pagination_and_export_report_saved_data(): void
    {
        $product=$this->product();
        $this->video($product,['title'=>'Target Lifestyle','category'=>'lifestyle']);
        for($i=0;$i<10;$i++) $this->video($product,['title'=>'Draft cap '.$i],false);
        $this->actingAs($this->admin())->get('/admin/resource/videos?q=Target&category=lifestyle&status=published&platform=Website&product_id='.$product->id)
            ->assertOk()->assertSee('Target Lifestyle')->assertDontSee('Draft cap 0')->assertSee('of 1 videos');
        $this->get('/admin/resource/videos?per_page=8&page=2')->assertOk()->assertSee('Showing 9 to 11 of 11 videos');
        $response=$this->get('/admin/resource/videos/export?q=Target')->assertOk()->assertDownload();
        $this->assertStringContainsString('Target Lifestyle',$response->streamedContent());
        $this->assertStringNotContainsString('Draft cap 0',$response->streamedContent());
    }

    public function test_update_preserves_uuid_and_moves_legacy_public_files_behind_access_checks(): void
    {
        $product=$this->product();
        Storage::disk('public')->put('product-media/legacy.mp4','legacy file');
        $video=ProductVideo::create(['product_id'=>$product->id,'disk'=>'public','path'=>'product-media/legacy.mp4','active'=>true,'alt_text'=>'Old title']);
        $uuid=$video->uuid;
        $this->actingAs($this->admin())->patchJson('/admin/resource/videos/'.$video->id,$this->payload($product,['title'=>'Updated title','visibility'=>'private','status'=>'published']))
            ->assertOk()->assertJsonPath('video.uuid',$uuid)->assertJsonPath('video.title','Updated title');
        $video->refresh();
        $this->assertSame('local',$video->disk);
        Storage::disk('local')->assertExists($video->path);
        $this->assertDatabaseCount('product_media',1);
        $this->get('/admin/resource/videos/'.$video->id.'/audit')->assertOk()->assertJsonPath('entries.0.action','video.updated');
        Auth::logout();
        $this->get(route('videos.asset',[$uuid,'video']))->assertNotFound();
    }

    public function test_bulk_changes_are_audited_atomic_and_cannot_target_images(): void
    {
        $product=$this->product(); $video=$this->video($product,['visibility'=>'private'],false);
        $image=ProductMedia::create(['product_id'=>$product->id,'type'=>'image','disk'=>'public','path'=>'product-media/image.jpg']);
        $this->actingAs($this->admin())->postJson('/admin/resource/videos/bulk',['ids'=>[$video->id,$image->id],'action'=>'publish'])->assertUnprocessable();
        $this->assertFalse($video->fresh()->active);
        $this->patchJson('/admin/resource/videos/'.$image->id,$this->payload($product))->assertNotFound();
        $this->deleteJson('/admin/resource/videos/'.$image->id)->assertNotFound();
        $this->postJson('/admin/resource/videos/bulk',['ids'=>[$video->id],'action'=>'publish'])->assertOk();
        $this->assertTrue($video->fresh()->active);
        $this->assertSame('private',$video->fresh()->metadata['visibility']);
        $this->postJson('/admin/resource/videos/bulk',['ids'=>[$video->id],'action'=>'delete'])->assertOk();
        $this->assertDatabaseMissing('product_media',['id'=>$video->id]);
        $this->assertDatabaseHas('product_media',['id'=>$image->id]);
        $this->assertDatabaseHas('audit_logs',['action'=>'video.deleted','subject_id'=>(string)$video->id]);
    }

    public function test_playback_metrics_are_deduplicated_wall_clock_bounded_and_exclude_admin_preview(): void
    {
        $video=$this->video($this->product());
        $url=route('videos.record',$video->uuid);
        $initial = $this->postJson($url,['seconds'=>0]);
        $initial->assertNoContent();
        // Keep the same session cookie across requests, just as the browser does.
        $sessionCookie = $initial->getCookie(config('session.cookie'));
        $this->withCookie(config('session.cookie'), $sessionCookie?->getValue());
        $this->postJson($url,['seconds'=>15])->assertNoContent();
        $this->assertDatabaseCount('video_plays',1);
        $this->assertSame(0,VideoPlay::firstOrFail()->seconds);
        $this->travel(10)->seconds();
        $this->postJson($url,['seconds'=>15])->assertNoContent();
        $this->assertSame(10,VideoPlay::firstOrFail()->seconds);
        $this->postJson($url,['seconds'=>10000])->assertUnprocessable();
        $this->actingAs($this->admin())->postJson($url,['seconds'=>10])->assertNoContent();
        $this->assertSame(10,VideoPlay::firstOrFail()->seconds);
        $this->get('/admin/resource/videos')->assertOk()->assertSee('Signature Cap Overview');
        $this->assertDatabaseCount('video_plays',1);
        $this->travelBack();
    }

    public function test_hidden_and_external_videos_do_not_accept_public_metrics(): void
    {
        $product=$this->product();
        $private=$this->video($product,['visibility'=>'private']);
        $external=$this->video($product,['platform'=>'YouTube']);
        foreach([$private,$external] as $video) $this->postJson(route('videos.record',$video->uuid),['seconds'=>0])->assertNotFound();
        $this->assertDatabaseCount('video_plays',0);
    }

    public function test_captions_use_a_safe_content_type_and_non_video_assets_cannot_be_served(): void
    {
        $product=$this->product();
        Storage::disk('local')->put('videos/captions/cap.vtt',"WEBVTT\n\n");
        $video=$this->video($product,['captions'=>'videos/captions/cap.vtt']);
        $this->get(route('videos.asset',[$video->uuid,'captions']))->assertOk()->assertHeader('Content-Type','text/vtt; charset=UTF-8');
        $this->get(route('videos.asset',[$video->uuid,'unknown']))->assertNotFound();
        $image=ProductMedia::create(['product_id'=>$product->id,'type'=>'image','disk'=>'public','path'=>'product-media/image.jpg']);
        $this->get('/videos/'.$image->uuid.'/assets/video')->assertNotFound();
    }
}
