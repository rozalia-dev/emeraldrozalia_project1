<?php

namespace Tests\Feature;

use Tests\TestCase;

class CareersWidthContractTest extends TestCase
{
    public function test_careers_page_uses_public_full_width_override(): void
    {
        $this->get('/careers')
            ->assertOk()
            ->assertSee('/css/careers-fullwidth.css?v=20260910-fullwidth', false);

        $css = file_get_contents(public_path('css/careers-fullwidth.css'));

        $this->assertStringContainsString('width:min(1536px,100%)', $css);
        $this->assertStringContainsString('max-width:1536px', $css);
    }
}
