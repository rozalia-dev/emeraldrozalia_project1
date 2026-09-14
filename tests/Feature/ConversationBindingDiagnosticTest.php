<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationBindingDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_communication_update_visibility_diagnostic(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $conversation = Conversation::create([
            'channel' => 'web',
            'contact' => 'diagnostic@example.com',
            'subject' => 'Diagnostic',
            'status' => 'new',
            'priority' => 'normal',
            'metadata' => ['name' => 'Diagnostic'],
        ]);

        $company = Company::create(['name' => 'Diagnostic Company', 'code' => 'DIAG-'.str()->random(8)]);
        session()->forget('company_id');

        $url = route('admin.communication.update', $conversation);
        $direct = Conversation::withoutGlobalScopes()->whereKey($conversation->getKey())->first();
        $response = $this->actingAs($admin)->patch($url, [
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $admin->id,
            'follow_up_at' => '2026-09-09 10:00',
        ]);

        fwrite(STDOUT, "\nDIAGNOSTIC url={$url} uuid={$conversation->uuid} id={$conversation->id} direct=".($direct ? 'yes' : 'no')." status={$response->status()} session_company=".var_export(session('company_id'), true)." admin=".var_export((bool) auth()->user()?->is_admin, true)."\n");
        fwrite(STDOUT, "DIAGNOSTIC body=".substr(preg_replace('/\s+/', ' ', $response->getContent()), 0, 600)."\n");

        $this->assertTrue(true);
    }
}
