<?php

namespace Tests\Feature;

use App\Events\CommunicationTemplateChanged;
use App\Models\AuditLog;
use App\Models\CommunicationTemplate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommunicationTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_api_is_uuid_keyed_tenant_scoped_and_idempotent(): void
    {
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ER-TPL-1', 'active' => true]);
        $otherCompany = Company::create(['name' => 'Other Company', 'code' => 'ER-TPL-2', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $payload = [
            'name' => 'Order confirmation',
            'subject' => 'Your Emerald Rozalia order is confirmed',
            'body' => 'Hello {{ customer_name }}, your order is confirmed.',
            'channel' => 'email',
            'status' => 'draft',
            'variables' => ['category' => 'Order', 'language' => 'English'],
        ];

        $create = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'template-contract-001')
            ->postJson(route('api.v1.communication.templates.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Order confirmation')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonMissingPath('data.id');

        $uuid = $create->json('data.uuid');
        $this->assertNotEmpty($uuid);
        $this->assertSame(1, CommunicationTemplate::query()->count());

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'template-contract-001')
            ->postJson(route('api.v1.communication.templates.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->withHeader('Idempotency-Key', 'template-contract-001')
            ->postJson(route('api.v1.communication.templates.store'), array_replace($payload, ['body' => 'Changed body']))
            ->assertStatus(409);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.templates.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $uuid)
            ->assertJsonMissingPath('data.0.id');

        $this->withSession(['company_id' => $otherCompany->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.templates.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withSession(['company_id' => $otherCompany->id])
            ->actingAs($admin)
            ->getJson(route('api.v1.communication.templates.show', ['template' => $uuid]))
            ->assertNotFound();
    }

    public function test_template_updates_are_versioned_audited_and_emit_after_commit(): void
    {
        Event::fake([CommunicationTemplateChanged::class]);
        $company = Company::create(['name' => 'Emerald Rozalia', 'code' => 'ER-TPL-3', 'active' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $create = $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->postJson(route('api.v1.communication.templates.store'), [
                'name' => 'Welcome',
                'subject' => 'Welcome to Emerald Rozalia',
                'body' => 'Private internal copy {{ customer_name }}',
                'status' => 'draft',
            ])
            ->assertCreated();
        $uuid = $create->json('data.uuid');

        Event::assertDispatched(CommunicationTemplateChanged::class, fn (CommunicationTemplateChanged $event): bool => $event->action === 'created' && $event->template->uuid === $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patchJson(route('api.v1.communication.templates.update', ['template' => $uuid]), [
                'subject' => 'Welcome — updated',
                'body' => 'Updated copy {{ customer_name }}',
                'expected_version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        Event::assertDispatched(CommunicationTemplateChanged::class, fn (CommunicationTemplateChanged $event): bool => $event->action === 'updated' && $event->template->uuid === $uuid);

        $this->withSession(['company_id' => $company->id])
            ->actingAs($admin)
            ->patchJson(route('api.v1.communication.templates.update', ['template' => $uuid]), [
                'subject' => 'Stale update',
                'expected_version' => 1,
            ])
            ->assertStatus(409);

        $audit = AuditLog::query()
            ->where('subject_uuid', $uuid)
            ->where('action', 'communication.email-template.updated')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame($admin->public_uuid, $audit->actor_uuid);
        $this->assertSame($uuid, $audit->subject_uuid);
        $this->assertArrayNotHasKey('body', $audit->after);
        $this->assertNotEmpty($audit->after['body_sha256']);
    }
}
