<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExportFormatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_export_keeps_csv_and_adds_real_pdf_output(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $csv = $this->actingAs($admin)->get(route('admin.reports.export', [
            'format' => 'csv',
            'name' => 'export-format-contract',
        ]));

        $csv->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $csv->headers->get('Content-Type')));

        $pdf = $this->actingAs($admin)->get(route('admin.reports.export', [
            'format' => 'pdf',
            'name' => 'export-format-contract',
        ]));

        $pdf->assertOk();
        $this->assertSame('application/pdf', strtolower((string) $pdf->headers->get('Content-Type')));
        $this->assertStringContainsString('.pdf', strtolower((string) $pdf->headers->get('Content-Disposition')));
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdf->getContent());
        $this->assertStringContainsString('%%EOF', (string) $pdf->getContent());
    }

    public function test_customer_csv_export_also_inherits_pdf_without_changing_its_controller(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false, 'name' => 'Export Contract Customer']);

        $pdf = $this->actingAs($admin)->get(route('admin.customers.export', ['format' => 'pdf']));

        $pdf->assertOk();
        $this->assertSame('application/pdf', strtolower((string) $pdf->headers->get('Content-Type')));
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdf->getContent());
    }

    public function test_admin_html_loads_the_universal_export_pair_assets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.reports.overview'))
            ->assertOk()
            ->assertSee('/css/admin-export-formats.css?v=20260914-1', false)
            ->assertSee('/js/admin-export-formats.js?v=20260914-1', false);
    }
}
