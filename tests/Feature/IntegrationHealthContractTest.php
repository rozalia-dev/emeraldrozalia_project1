<?php

namespace Tests\Feature;

use App\Models\{Company, IntegrationConnection, User};
use App\Services\{ExternalServiceGate, IntegrationConnectionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationHealthContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_never_reports_a_disabled_or_unverified_provider_as_healthy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config(['external.payment' => [
            'enabled' => false,
            'provider' => 'Stripe',
            'key' => 'key',
            'secret' => 'secret',
            'webhook_secret' => 'signature',
        ]]);

        $connection = IntegrationConnection::create([
            'service' => 'payment',
            'provider' => 'Stripe',
            'enabled' => true,
        ]);
        $this->actingAs($admin);
        $probe = app(IntegrationConnectionService::class)->probe($connection);

        $this->assertSame('blocked', $probe['health']);
        $this->assertFalse($probe['runtime_live']);
        $this->assertDatabaseHas('integration_connections', [
            'service' => 'payment',
            'health' => 'blocked',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.integration.probed']);
    }

    public function test_ready_means_configuration_gate_passed_and_status_endpoint_is_tenant_scoped(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create([
            'name' => 'Integration Tenant',
            'code' => 'integration-tenant',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        config(['external.payment' => [
            'enabled' => true,
            'provider' => 'Stripe',
            'key' => 'key',
            'secret' => 'secret',
            'webhook_secret' => 'signature',
        ]]);

        $connection = IntegrationConnection::create([
            'company_id' => $company->id,
            'service' => 'payment',
            'provider' => 'Stripe',
            'enabled' => false,
        ]);
        $this->actingAs($admin)->withSession(['company_id' => $company->id]);
        $probe = app(IntegrationConnectionService::class)->probe($connection);
        $this->assertSame('ready', $probe['health']);
        $this->assertTrue($probe['runtime_live']);

        $response = $this->get(route('admin.integration-status'));
        $response->assertOk()
            ->assertJsonPath('data.payment.health', 'ready')
            ->assertJsonPath('data.payment.runtime_live', true)
            ->assertJsonPath('data.payment.enabled', false)
            ->assertJsonPath('probe_semantics', 'Configuration and activation readiness only; no provider is labelled healthy without an external handshake or signed callback evidence.');
        $response->assertJsonMissingPath('data.payment.company_id');
    }

    public function test_external_gate_exposes_missing_configuration_without_secret_values(): void
    {
        config(['external.shipping' => [
            'enabled' => true,
            'provider' => 'Shippo',
            'key' => '',
            'secret' => '',
        ]]);

        $status = app(ExternalServiceGate::class)->status('shipping');

        $this->assertFalse($status['configured']);
        $this->assertFalse($status['live']);
        $this->assertSame(['key', 'secret'], $status['missing']);
        $this->assertArrayNotHasKey('key', $status);
        $this->assertArrayNotHasKey('secret', $status);
    }
}
