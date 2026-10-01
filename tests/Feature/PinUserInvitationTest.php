<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\PinUserInvitationMail;
use App\Models\User;
use App\Notifications\PinUserInvitationNotification;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * An Admin or Staff account added with an email gets an invitation email
 * too (2026-09-30), not only a starting PIN handed over in person. The PIN
 * itself never goes in the email.
 */
class PinUserInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
    }

    private function addUser(array $fields)
    {
        return $this->actingAs($this->superadmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.users.store'), $fields + ['pin' => '4829', 'pin_confirmation' => '4829']);
    }

    public function test_staff_added_with_an_email_gets_an_invitation_email(): void
    {
        Notification::fake();

        $this->addUser(['name' => 'Drei', 'role' => 'staff', 'email' => 'drei@example.com'])
            ->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'drei@example.com'));

        $drei = User::where('email', 'drei@example.com')->sole();
        Notification::assertSentTo($drei, PinUserInvitationNotification::class);
        Notification::assertNotSentTo($drei, UserInvitationNotification::class);
        $this->assertTrue($drei->hasPin(), 'The starting PIN is still set as before.');
    }

    public function test_admin_added_with_an_email_gets_one_too(): void
    {
        Notification::fake();

        $this->addUser(['name' => 'Ana', 'role' => 'admin', 'email' => 'ana@example.com'])->assertSessionHasNoErrors();

        Notification::assertSentTo(User::where('email', 'ana@example.com')->sole(), PinUserInvitationNotification::class);
    }

    public function test_staff_added_without_an_email_gets_nothing(): void
    {
        Notification::fake();

        $this->addUser(['name' => 'Lito', 'role' => 'staff', 'email' => ''])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_the_email_links_to_sign_in_and_never_contains_the_pin(): void
    {
        $drei = User::factory()->create(['name' => 'Drei', 'role' => UserRole::Staff, 'email' => 'drei@example.com']);
        $drei->setPin('4829', temporary: true);

        $html = (new PinUserInvitationMail($drei))->render();

        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString('Drei', $html);
        $this->assertStringContainsString('Staff', $html);
        $this->assertStringNotContainsString('4829', $html, 'The starting PIN is never emailed.');
    }

    public function test_a_mail_failure_still_keeps_the_account_and_warns(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->addUser(['name' => 'Drei', 'role' => 'staff', 'email' => 'drei@example.com'])
            ->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'could not be sent'));

        $this->assertTrue(User::where('email', 'drei@example.com')->exists(), 'The account is kept.');
    }
}
