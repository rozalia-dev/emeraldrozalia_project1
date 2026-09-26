<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagerDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_manager_uses_date_pickers_and_filters_products_by_creation_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->product('Before Range Cap', '2026-09-09 12:00:00');
        $this->product('Within Range Cap', '2026-09-15 12:00:00');
        $this->product('After Range Cap', '2026-09-21 12:00:00');

        $response = $this->actingAs($admin)->get(route('admin.resource', [
            'module' => 'product-manager',
            'from' => '2026-09-10',
            'to' => '2026-09-20',
        ]));

        $viewPath = resource_path('views/admin/product-manager/index.blade.php');
        $compiledPath = app('blade.compiler')->getCompiledPath($viewPath);
        $html = $response->getContent();
        $debug = [
            'source_has_from_date' => str_contains(file_get_contents($viewPath), 'type="date" name="from"'),
            'compiled_has_from_date' => is_file($compiledPath) && str_contains(file_get_contents($compiledPath), 'type="date"'),
            'html_has_from_date' => str_contains($html, 'type="date" name="from"'),
            'html_from_position' => strpos($html, 'name="from"'),
            'html_date_position' => strpos($html, 'type="date"'),
        ];
        fwrite(STDERR, '[PMDATEDEBUG] '.json_encode($debug)."\n");

        $response->assertOk()
            ->assertSee('type="date" name="from"', false)
            ->assertSee('type="date" name="to"', false)
            ->assertSee('Within Range Cap')
            ->assertDontSee('Before Range Cap')
            ->assertDontSee('After Range Cap');
    }

    private function product(string $name, string $createdAt): Product
    {
        $slug = strtolower(str_replace(' ', '-', $name));

        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'sku' => strtoupper(str_replace('-', '', $slug)),
            'price' => 24.99,
            'stock' => 12,
            'brand' => 'Emerald Rozalia',
            'status' => 'active',
            'is_active' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
