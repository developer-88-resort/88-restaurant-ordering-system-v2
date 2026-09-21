<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PinUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
    }

    public function test_a_staff_member_is_created_with_a_starting_pin_and_no_email(): void
    {
        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.store'), [
                'name' => 'Lito Waiter',
                'role' => 'staff',
                'email' => '',
                'pin' => '4829',
                'pin_confirmation' => '4829',
            ])
            ->assertRedirect(route('superadmin.users.index'))
            ->assertSessionHasNoErrors();

        $staff = User::where('name', 'Lito Waiter')->sole();
        $this->assertNull($staff->email);
        $this->assertNull($staff->password);
        $this->assertTrue($staff->checkPin('4829'));
        $this->assertNull($staff->pin_changed_at, 'A starting PIN has to be replaced at first sign-in.');
        $this->assertFalse($staff->isPendingActivation());
        Notification::assertNothingSent();

        $this->assertTrue(Activity::where('event', 'pin_reset')->where('subject_id', $staff->id)->exists());

        // Their first sign-in works with it, then sends them to choose their own.
        $this->post(route('logout'));
        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('pin.setup'));
    }

    public function test_the_starting_pin_follows_the_same_rules(): void
    {
        User::factory()->withPin('5831')->create(['role' => UserRole::Staff]);

        foreach (['1234', '5831'] as $pin) {
            $this->asConfirmedSuperadmin()
                ->post(route('superadmin.users.store'), ['name' => 'Lito', 'role' => 'staff', 'pin' => $pin, 'pin_confirmation' => $pin])
                ->assertSessionHasErrors('pin');
        }

        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.store'), ['name' => 'Lito', 'role' => 'staff', 'pin' => '4829', 'pin_confirmation' => '4830'])
            ->assertSessionHasErrors('pin');

        $this->assertFalse(User::where('name', 'Lito')->exists());
    }

    public function test_a_superadmin_is_still_invited_by_email(): void
    {
        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.store'), ['name' => 'New Owner', 'role' => 'superadmin', 'email' => ''])
            ->assertSessionHasErrors('email');

        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.store'), ['name' => 'New Owner', 'role' => 'superadmin', 'email' => 'owner@example.com'])
            ->assertRedirect(route('superadmin.users.index'));

        $owner = User::where('email', 'owner@example.com')->sole();
        $this->assertNull($owner->pin_hash);
        $this->assertTrue($owner->isPendingActivation());
        Notification::assertSentTo($owner, UserInvitationNotification::class);
    }

    public function test_resetting_a_pin_gives_a_starting_pin_and_lifts_a_lockout(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '0001']);
        }
        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])->assertSessionHasErrors('pin');

        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.reset-pin', $staff), ['pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertRedirect(route('superadmin.users.index'));

        $staff->refresh();
        $this->assertTrue($staff->checkPin('7351'));
        $this->assertNull($staff->pin_changed_at);
        $this->assertSame(1, Activity::where('event', 'pin_reset')->where('subject_id', $staff->id)->count());

        $this->post(route('logout'));
        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '7351'])
            ->assertRedirect(route('pin.setup'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_a_superadmins_pin_cannot_be_reset(): void
    {
        $other = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->asConfirmedSuperadmin()
            ->post(route('superadmin.users.reset-pin', $other), ['pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertSessionHas('error');

        $this->assertNull($other->refresh()->pin_hash);
    }

    public function test_only_a_superadmin_manages_pins(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.users.reset-pin', $staff), ['pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertForbidden();
    }

    public function test_email_is_optional_for_staff_but_a_pin_only_account_cannot_become_superadmin(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff, 'password' => null]);

        $this->asConfirmedSuperadmin()
            ->put(route('superadmin.users.update', $staff), ['name' => $staff->name, 'email' => '', 'role' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertNull($staff->refresh()->email);
        $this->assertSame(UserRole::Admin, $staff->role);

        $this->asConfirmedSuperadmin()
            ->put(route('superadmin.users.update', $staff), ['name' => $staff->name, 'email' => 'lito@example.com', 'role' => 'superadmin'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $staff->refresh()->role);
    }

    private function asConfirmedSuperadmin(): static
    {
        return $this->actingAs($this->superadmin)->withSession(['auth.password_confirmed_at' => time()]);
    }
}
