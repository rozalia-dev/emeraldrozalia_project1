<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductCatalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_publish_and_replace_the_product_catalogue(): void
    {
        Storage::fake('local');
        [$admin, $company] = $this->adminTenant('Catalogue Tenant', 'CATALOGUE');

        $this->withTenant($admin, $company)
            ->get(route('admin.product-catalogue.index'))
            ->assertOk()
            ->assertSeeText('Product Catalogue')
            ->assertSeeText('Public downloads');

        $pdf = UploadedFile::fake()->createWithContent('emerald-catalogue.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $cover = UploadedFile::fake()->image('catalogue-cover.jpg', 600, 800);

        $this->withTenant($admin, $company)->put(route('admin.product-catalogue.update'), [
            'title' => 'Emerald Rozalia Product Catalogue',
            'version' => 'Autumn 2026',
            'description' => 'Current hats and caps catalogue.',
            'catalogue_pdf' => $pdf,
            'cover_image' => $cover,
            'is_published' => '1',
        ])->assertRedirect(route('admin.product-catalogue.index'));

        $catalogue = ProductCatalogue::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $this->assertTrue($catalogue->is_published);
        $this->assertNotNull($catalogue->published_at);
        Storage::disk('local')->assertExists($catalogue->pdf_path);
        Storage::disk('local')->assertExists($catalogue->cover_path);

        $this->withSession(['company_id' => $company->id])->get(route('catalogue.show'))
            ->assertOk()
            ->assertSeeText('Emerald Rozalia Product Catalogue')
            ->assertSeeText('Autumn 2026')
            ->assertSeeText('DOWNLOAD PRODUCT CATALOGUE');

        $this->withSession(['company_id' => $company->id])->get(route('catalogue.download'))->assertOk();
        $this->assertSame(1, (int) $catalogue->fresh()->download_count);

        $oldPdf = $catalogue->pdf_path;
        $replacement = UploadedFile::fake()->createWithContent('emerald-catalogue-v2.pdf', "%PDF-1.4\n2 0 obj\n<<>>\nendobj\n%%EOF");
        $this->withTenant($admin, $company)->put(route('admin.product-catalogue.update'), [
            'title' => 'Emerald Rozalia Product Catalogue',
            'version' => 'Winter 2026',
            'description' => 'Updated hats and caps catalogue.',
            'catalogue_pdf' => $replacement,
            'is_published' => '1',
        ])->assertRedirect(route('admin.product-catalogue.index'));

        $catalogue->refresh();
        Storage::disk('local')->assertMissing($oldPdf);
        Storage::disk('local')->assertExists($catalogue->pdf_path);
        $this->assertSame('Winter 2026', $catalogue->version);
    }

    public function test_unpublished_catalogue_is_not_public_and_tenants_are_isolated(): void
    {
        Storage::fake('local');
        [$admin, $first] = $this->adminTenant('First Catalogue Tenant', 'CAT-FIRST');
        $second = Company::create(['name' => 'Second Catalogue Tenant', 'code' => 'CAT-SECOND', 'active' => true]);
        $pdf = UploadedFile::fake()->createWithContent('private-catalogue.pdf', "%PDF-1.4\n%%EOF");

        $this->withTenant($admin, $first)->put(route('admin.product-catalogue.update'), [
            'title' => 'Private Catalogue',
            'version' => 'Draft',
            'catalogue_pdf' => $pdf,
        ])->assertRedirect(route('admin.product-catalogue.index'));

        $catalogue = ProductCatalogue::withoutGlobalScopes()->where('company_id', $first->id)->firstOrFail();
        $this->assertFalse($catalogue->is_published);
        $this->withSession(['company_id' => $first->id])->get(route('catalogue.show'))->assertNotFound();

        $this->withTenant($admin, $first)->put(route('admin.product-catalogue.update'), [
            'title' => 'Private Catalogue',
            'version' => 'Published',
            'is_published' => '1',
        ])->assertRedirect(route('admin.product-catalogue.index'));

        $this->withSession(['company_id' => $first->id])->get(route('catalogue.show'))->assertOk();
        $this->withSession(['company_id' => $second->id])->get(route('catalogue.show'))->assertNotFound();
    }

    public function test_catalogue_cannot_be_published_without_a_pdf(): void
    {
        [$admin, $company] = $this->adminTenant('Empty Catalogue Tenant', 'CAT-EMPTY');

        $this->withTenant($admin, $company)->put(route('admin.product-catalogue.update'), [
            'title' => 'Catalogue Without File',
            'is_published' => '1',
        ])->assertSessionHasErrors('catalogue_pdf');

        $this->assertDatabaseMissing('product_catalogues', ['company_id' => $company->id, 'is_published' => true]);
    }

    private function adminTenant(string $name, string $code): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => $name, 'code' => $code, 'active' => true]);

        return [$admin, $company];
    }

    private function withTenant(User $admin, Company $company): self
    {
        return $this->withSession(['company_id' => $company->id])->actingAs($admin);
    }
}
