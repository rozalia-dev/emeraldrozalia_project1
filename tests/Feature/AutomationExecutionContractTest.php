<?php

namespace Tests\Feature;

use App\Models\{AutomationRun, Company, CommunicationAction, User};
use App\Services\AutomationRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationExecutionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_rule_executes_durable_action_once_and_conditions_can_skip(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create([
            'name' => 'Automation Tenant',
            'code' => 'automation-tenant',
            'country_code' => 'IE',
            'base_currency' => 'EUR',
            'default_locale' => 'en',
            'active' => true,
        ]);
        $service = app(AutomationRuleService::class);

        $rule = $service->create([
            'company_id' => $company->id,
            'name' => 'Create quote follow-up',
            'event' => 'quote.converted',
            'actions' => 'Create task',
            'conditions' => [],
            'enabled' => true,
        ]);

        $this->actingAs($admin);
        $runs = $service->run('quote.converted', [
            'company_id' => $company->id,
            'quote_uuid' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Quote ready for fulfilment',
        ], 'quote:11111111-1111-4111-8111-111111111111', $company->id);

        $this->assertCount(1, $runs);
        $this->assertSame('completed', $runs->first()->status);
        $this->assertDatabaseHas('automation_runs', ['automation_rule_id' => $rule->id, 'status' => 'completed']);
        $this->assertDatabaseHas('communication_actions', ['company_id' => $company->id, 'title' => 'Quote ready for fulfilment']);

        $service->run('quote.converted', [
            'company_id' => $company->id,
            'quote_uuid' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Quote ready for fulfilment',
        ], 'quote:11111111-1111-4111-8111-111111111111', $company->id);
        $this->assertSame(1, CommunicationAction::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $skippingRule = $service->create([
            'company_id' => $company->id,
            'name' => 'Only corporate orders',
            'event' => 'order.created',
            'actions' => 'Create alert',
            'conditions' => ['order_type' => 'corporate'],
            'enabled' => true,
        ]);
        $skipped = $service->run('order.created', [
            'company_id' => $company->id,
            'order_type' => 'online',
        ], 'order:online-1', $company->id)->last();
        $this->assertSame($skippingRule->id, $skipped->automation_rule_id);
        $this->assertSame('skipped', $skipped->status);
    }

    public function test_supported_event_is_queued_with_tenant_context(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        app(AutomationRuleService::class)->queue(
            'quote.converted',
            ['company_id' => 42, 'quote_uuid' => '22222222-2222-4222-8222-222222222222'],
            'quote:22222222-2222-4222-8222-222222222222',
        );

        Queue::assertPushed(\App\Jobs\RunAutomationRules::class, function ($job): bool {
            return $job->event === 'quote.converted'
                && $job->companyId === 42
                && $job->eventKey === 'quote:22222222-2222-4222-8222-222222222222';
        });
    }
}
