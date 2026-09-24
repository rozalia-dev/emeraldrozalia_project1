<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BulkProductImageZipUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_import_product_with_six_images_from_zip(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_admin' => true]);
        $gift = Category::create([
            'name' => 'Gift for Her',
            'slug' => 'gift',
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => 7,
        ]);

        $csvPath = tempnam(sys_get_temp_dir(), 'bulk-products-');
        $zipPath = tempnam(sys_get_temp_dir(), 'bulk-images-');

        $headers = [
            'Product Name', 'SKU', 'Category', 'Price', 'Stock', 'Status',
            'Image 1', 'Image 2', 'Image 3', 'Image 4', 'Image 5', 'Image 6',
        ];
        $row = [
            'Gift for Her Test Hat', 'ER-GFH-900', 'Gift for Her', '75.00', '3', 'published',
            'view-01.png', 'view-02.png', 'view-03.png', 'view-04.png', 'view-05.png', 'view-06.png',
        ];

        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, $headers);
        fputcsv($handle, $row);
        fclose($handle);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        );

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach (range(1, 6) as $position) {
            $zip->addFromString(
                'ER-GFH-900/view-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT).'.png',
                $png
            );
        }
        $zip->close();

        try {
            $response = $this->actingAs($admin)->post(route('admin.bulk-upload.store'), [
                'file' => new UploadedFile($csvPath, 'gift-for-her.csv', 'text/csv', null, true),
                'images_zip' => new UploadedFile($zipPath, 'gift-for-her-images.zip', 'application/zip', null, true),
                'approve_images' => '1',
                'default_status' => 'Published',
            ]);

            $response->assertRedirect()->assertSessionHas('result');

            $product = Product::query()->where('sku', 'ER-GFH-900')->firstOrFail();
            $this->assertSame($gift->id, $product->category_id);
            $this->assertSame('published', $product->status);
            $this->assertTrue((bool) $product->is_active);
            $this->assertSame(75.0, (float) $product->price);
            $this->assertNotNull($product->image);

            $media = ProductMedia::query()
                ->where('product_id', $product->id)
                ->orderBy('sort_order')
                ->get();

            $this->assertCount(6, $media);
            $this->assertSame([1, 2, 3, 4, 5, 6], $media->pluck('sort_order')->all());
            $this->assertTrue($media->every(fn (ProductMedia $item): bool => $item->approval_status === 'approved'));
            $this->assertTrue($media->every(fn (ProductMedia $item): bool => (bool) $item->active));

            foreach ($media as $item) {
                Storage::disk('public')->assertExists($item->path);
            }

            $this->assertDatabaseMissing('categories', ['slug' => 'gift-for-her']);
            $this->assertSame(6, (int) session('result.images_imported'));
            $this->assertSame(1, (int) session('result.products_with_images'));
        } finally {
            @unlink($csvPath);
            @unlink($zipPath);
        }
    }

    public function test_bulk_upload_page_exposes_image_zip_workflow(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.bulk-upload'))
            ->assertOk()
            ->assertSee(['Product Images ZIP', 'Image 1…Image 6', 'Approve imported images', 'Up to six images are attached per product'], false)
            ->assertSee('name="images_zip"', false)
            ->assertSee('name="approve_images"', false);
    }
}
