<?php

namespace Tests\Feature;

use App\Models\{Category, Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CategoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace([
            'name' => 'Caps',
            'slug' => 'caps',
            'parent_id' => null,
            'status' => 'active',
            'is_visible' => '1',
            'sort_order' => 1,
            'description' => 'Premium caps.',
            'meta_title' => 'Caps — Emerald Rozalia',
            'meta_description' => 'Shop premium caps.',
        ], $extra);
    }

    public function test_dashboard_requires_admin_and_renders_mockup_sections(): void
    {
        $this->get('/admin/resource/categories')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/resource/categories')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/resource/categories')->assertOk()->assertSee([
            'Categories','Category Tree','Category Details','Category Summary','Quick Actions','Category Visibility','UUID Traceability','Intuitive Hierarchy',
        ]);
    }

    public function test_create_hierarchy_visibility_and_audit_are_persisted(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/resource/categories', $this->payload())->assertRedirect();
        $parent = Category::where('slug', 'caps')->firstOrFail();
        $this->assertNotEmpty($parent->uuid);
        $this->assertTrue($parent->is_active);
        $this->assertTrue($parent->is_visible);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created', 'subject_id' => (string) $parent->id]);

        $this->post('/admin/resource/categories', $this->payload([
            'name' => 'Baseball Caps','slug' => 'baseball-caps','parent_id' => $parent->id,'sort_order' => 2,
        ]))->assertRedirect();
        $child = Category::where('slug', 'baseball-caps')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);

        $this->post('/admin/resource/categories', $this->payload([
            'name' => 'Hidden Caps','slug' => 'hidden-caps','is_visible' => '0','sort_order' => 3,
        ]))->assertRedirect();
        $hidden = Category::where('slug', 'hidden-caps')->firstOrFail();
        $this->assertFalse($hidden->is_visible);
        $this->assertFalse($hidden->is_active, 'Hidden active-status categories must not leak into legacy storefront is_active queries.');

        $this->get('/admin/resource/categories?selected='.$child->uuid)->assertOk()->assertSee('Baseball Caps')->assertSee('Caps');
    }

    public function test_update_blocks_circular_hierarchy(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/resource/categories', $this->payload())->assertRedirect();
        $parent = Category::where('slug', 'caps')->firstOrFail();
        $this->post('/admin/resource/categories', $this->payload(['name'=>'Child','slug'=>'child','parent_id'=>$parent->id]))->assertRedirect();
        $child = Category::where('slug', 'child')->firstOrFail();

        $this->patch('/admin/resource/categories/'.$parent->uuid, $this->payload(['parent_id'=>$child->id]))->assertSessionHasErrors('parent_id');
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_reorder_bulk_visibility_and_export_work(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/resource/categories', $this->payload())->assertRedirect();
        $first = Category::where('slug', 'caps')->firstOrFail();
        $this->post('/admin/resource/categories', $this->payload(['name'=>'Hats','slug'=>'hats','sort_order'=>2]))->assertRedirect();
        $second = Category::where('slug', 'hats')->firstOrFail();

        $this->postJson('/admin/resource/categories/reorder', ['order'=>[
            ['id'=>$first->id,'sort_order'=>2],['id'=>$second->id,'sort_order'=>1],
        ]])->assertOk()->assertJson(['ok'=>true]);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);

        $this->post('/admin/resource/categories/bulk', ['ids'=>[$first->id],'action'=>'hidden'])->assertRedirect();
        $this->assertFalse($first->fresh()->is_visible);
        $this->assertFalse($first->fresh()->is_active);
        $this->post('/admin/resource/categories/bulk', ['ids'=>[$first->id],'action'=>'visible'])->assertRedirect();
        $this->assertTrue($first->fresh()->is_visible);
        $this->assertTrue($first->fresh()->is_active);

        $csv = $this->get('/admin/resource/categories/export')->assertOk();
        $this->assertStringContainsString('parent_slug', $csv->streamedContent());
        $this->assertStringContainsString('caps', $csv->streamedContent());
    }

    public function test_import_builds_parent_relationship_and_delete_is_safe(): void
    {
        $this->actingAs($this->admin());
        $csv = "name,slug,parent_slug,status,visibility,sort_order,description,meta_title,meta_description\nCaps,caps,,active,visible,1,Parent,Parent SEO,Parent meta\nBaseball Caps,baseball-caps,caps,active,visible,2,Child,Child SEO,Child meta\n";
        $this->post('/admin/resource/categories/import', ['file'=>UploadedFile::fake()->createWithContent('categories.csv', $csv)])->assertRedirect();
        $parent = Category::where('slug', 'caps')->firstOrFail();
        $child = Category::where('slug', 'baseball-caps')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);

        Product::create(['category_id'=>$child->id,'name'=>'Mapped Cap','slug'=>'mapped-cap','sku'=>'CAT-001','price'=>20,'stock'=>2,'is_active'=>true]);
        $this->delete('/admin/resource/categories/'.$child->uuid)->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id'=>$child->id]);
    }

    public function test_audit_endpoint_is_uuid_addressed_and_deterministic(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/resource/categories', $this->payload())->assertRedirect();
        $category = Category::where('slug', 'caps')->firstOrFail();
        $this->patch('/admin/resource/categories/'.$category->uuid, $this->payload(['description'=>'Updated description']))->assertRedirect();
        $this->get('/admin/resource/categories/'.$category->uuid.'/audit')
            ->assertOk()->assertJsonPath('uuid', $category->uuid)->assertJsonPath('entries.0.action', 'category.updated');
    }
}
