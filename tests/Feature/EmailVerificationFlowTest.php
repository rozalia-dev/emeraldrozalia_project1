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

    public function test_registration_sends_verification_link_and_keeps_verification_reminder_in_account(): void
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

        $response->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(['Verify your email address', 'RESEND EMAIL'], false);
    }

    public function test_signed_verification_link_verifies_and_logs_in_a_guest_customer(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->assertGuest();

        $this->get($verificationUrl)
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHas('success', 'Email verified successfully.');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertDontSee('Verify your email address', false);
    }

    public function test_signed_verification_link_rejects_a_mismatched_email_hash(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('different@example.test')]
        );

        $this->get($verificationUrl)->assertForbidden();
        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
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
