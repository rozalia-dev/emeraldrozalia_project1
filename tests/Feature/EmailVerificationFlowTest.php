<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_link_and_redirects_to_verification_notice(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Verification Customer',
            'email' => 'verify@example.test',
            'phone' => '+353800000000',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $user = User::query()->where('email', 'verify@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_customer_is_kept_out_of_account_until_link_is_used(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('account.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect('/account');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())
            ->get(route('account.dashboard'))
            ->assertOk();
    }

    public function test_resend_endpoint_sends_another_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_forgot_password_uses_the_same_transactional_mail_channel(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
