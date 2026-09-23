<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Pin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_screen_lists_only_active_staff_and_admin_who_have_a_pin(): void
    {
        $staff = User::factory()->create(['name' => 'Ana Staff', 'role' => UserRole::Staff]);
        $admin = User::factory()->create(['name' => 'Ben Admin', 'role' => UserRole::Admin]);
        User::factory()->create(['name' => 'Cora Superadmin', 'role' => UserRole::Superadmin]);
        User::factory()->create(['name' => 'Dan Deactivated', 'role' => UserRole::Staff, 'is_active' => false]);
        User::factory()->withoutPin()->create(['name' => 'Eve No PIN', 'role' => UserRole::Staff]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->has('pinUsers', 2)
                ->where('pinUsers.0.id', $staff->id)
                ->where('pinUsers.0.name', 'Ana Staff')
                ->where('pinUsers.1.id', $admin->id)
                ->missing('pinUsers.0.pin_hash')
                ->missing('pinUsers.0.email')
                ->where('pinLength', ['min' => 4, 'max' => 6]));
    }

    public function test_staff_signs_in_by_name_and_pin(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($staff);
        $this->assertTrue(Activity::where('event', 'login')->where('subject_id', $staff->id)->exists());
    }

    public function test_a_wrong_pin_is_refused_and_written_to_the_audit_log(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4830'])
            ->assertSessionHasErrors(['pin' => 'Wrong PIN. Please try again.']);

        $this->assertGuest();

        $failure = Activity::where('event', 'failed_pin_login')->sole();
        $this->assertSame($staff->id, $failure->subject_id);
        $this->assertNull($failure->causer_id);
        $this->assertSame(1, $failure->properties['attempt']);
    }

    public function test_five_wrong_pins_lock_that_name_for_five_minutes_even_against_the_right_pin(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '0001'])
                ->assertSessionHasErrors(['pin' => 'Wrong PIN. Please try again.']);
        }

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '0001'])
            ->assertSessionHasErrors(['pin' => 'Too many wrong PINs. Try again in 5 min.']);

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest();

        $this->assertSame(5, Activity::where('event', 'failed_pin_login')->count());
        $lockout = Activity::where('event', 'pin_lockout')->sole();
        $this->assertSame($staff->id, $lockout->subject_id);
        $this->assertSame('name', $lockout->properties['scope']);

        $this->travel(301)->seconds();

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('profile.edit'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_one_device_cannot_walk_a_guessed_pin_down_the_name_list(): void
    {
        config(['auth.pin.device_max_attempts' => 6]);

        $names = User::factory()->count(3)->create(['role' => UserRole::Staff]);
        $target = User::factory()->withPin('5831')->create(['role' => UserRole::Staff]);

        // Two wrong tries on each of three names: under the per-name limit,
        // but six from this device.
        foreach ($names as $user) {
            foreach (range(1, 2) as $attempt) {
                $this->post(route('login.pin'), ['user_id' => $user->id, 'pin' => '5831'])->assertSessionHasErrors('pin');
            }
        }

        $this->post(route('login.pin'), ['user_id' => $target->id, 'pin' => '5831'])
            ->assertSessionHasErrors(['pin' => 'Too many wrong PINs. Try again in 5 min.']);
        $this->assertGuest();

        $this->assertTrue(Activity::where('event', 'pin_lockout')->get()->contains(fn ($log) => $log->properties['scope'] === 'device'));
    }

    public function test_a_superadmin_cannot_sign_in_with_a_pin(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $superadmin->forceFill(['pin_hash' => bcrypt('4829'), 'pin_lookup' => Pin::lookup('4829'), 'pin_changed_at' => now()])->save();

        $this->post(route('login.pin'), ['user_id' => $superadmin->id, 'pin' => '4829'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in_with_its_pin(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff, 'is_active' => false]);

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    /**
     * A browser that was bounced off a Superadmin page keeps that page in the
     * session. Whoever signs in next must not be dropped on a bare 403.
     */
    public function test_a_page_the_new_signer_in_cannot_open_is_not_where_they_land(): void
    {
        $staff = User::factory()->withPin('4829', temporary: true)->create(['role' => UserRole::Staff]);

        // Sent to the sign-in screen from a page only a Superadmin may open.
        $this->get(route('superadmin.users.index'))->assertRedirect(route('login'));

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('pin.setup'));

        $this->post(route('pin.setup.store'), ['pin' => '5837', 'pin_confirmation' => '5837'])
            ->assertRedirect(route('profile.edit'));

        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_a_page_they_may_open_is_still_where_they_land(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->get(route('kitchen.index'))->assertRedirect(route('login'));

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('kitchen.index'));
    }

    public function test_a_superadmin_still_lands_on_the_page_they_asked_for(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'password' => 'Password#2026']);

        $this->get(route('superadmin.users.index'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $superadmin->email, 'password' => 'Password#2026'])
            ->assertRedirect(route('superadmin.users.index'));
    }

    public function test_a_pin_given_by_an_admin_must_be_replaced_before_anything_else(): void
    {
        $staff = User::factory()->withPin('4829', temporary: true)->create(['role' => UserRole::Staff]);

        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '4829'])
            ->assertRedirect(route('pin.setup'));
        $this->assertAuthenticatedAs($staff);

        $this->get(route('orders.index'))->assertRedirect(route('pin.setup'));
        $this->get(route('pin.setup'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/SetPin')
            ->where('replacingTemporaryPin', true));

        $this->post(route('pin.setup.store'), ['pin' => '4829', 'pin_confirmation' => '4829'])
            ->assertSessionHasErrors('pin');

        $this->post(route('pin.setup.store'), ['pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertRedirect(route('orders.index'));

        $staff->refresh();
        $this->assertNotNull($staff->pin_changed_at);
        $this->assertTrue($staff->checkPin('7351'));
        $this->assertTrue(Activity::where('event', 'pin_set')->where('subject_id', $staff->id)->exists());

        $this->get(route('orders.index'))->assertOk();
    }

    public function test_an_existing_account_without_a_pin_signs_in_with_email_and_then_sets_one(): void
    {
        $staff = User::factory()->withoutPin()->create(['role' => UserRole::Staff]);

        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('pin.setup'));

        $this->get(route('superadmin.dashboard'))->assertRedirect(route('pin.setup'));
        $this->getJson(route('orders.index'))->assertStatus(409);

        $this->post(route('pin.setup.store'), ['pin' => '7351', 'pin_confirmation' => '7351'])
            ->assertRedirect();
        $this->assertFalse($staff->refresh()->mustSetPin());

        $this->post(route('logout'));
        $this->post(route('login.pin'), ['user_id' => $staff->id, 'pin' => '7351'])
            ->assertRedirect(route('profile.edit'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_a_superadmin_keeps_email_and_password_and_is_never_asked_for_a_pin(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->post(route('login'), ['email' => $superadmin->email, 'password' => 'password'])
            ->assertRedirect(route('superadmin.dashboard'));

        $this->get(route('superadmin.dashboard'))->assertOk();
        $this->assertNull($superadmin->refresh()->pin_hash);
    }

    public function test_a_pin_only_account_is_pointed_to_the_name_list_from_the_email_form(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff, 'password' => null]);

        $this->post(route('login'), ['email' => $staff->email, 'password' => 'anything'])
            ->assertSessionHasErrors(['email' => 'This account signs in with a name and PIN. Tap your name on the sign-in screen.']);
    }

    public function test_the_pin_hash_never_reaches_the_browser(): void
    {
        $staff = User::factory()->withPin('4829')->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $staff->id)
                ->where('auth.user.uses_pin', true)
                ->missing('auth.user.pin_hash')
                ->missing('auth.user.pin_lookup'));
    }

    public function test_switch_user_signs_out_back_to_the_name_list(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->post(route('logout'), ['reason' => 'switch'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Signed out. Tap your name to sign in.');

        $this->assertGuest();
    }
}
