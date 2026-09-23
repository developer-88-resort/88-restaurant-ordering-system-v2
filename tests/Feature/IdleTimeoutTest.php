<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared tablets: a Staff/Admin session ends after 10 minutes without
 * activity (plus a minute's grace behind the browser's own timer).
 */
class IdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_session_idle_past_the_limit_is_signed_out(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->withSession([EnforceIdleTimeout::SESSION_KEY => now()->subMinutes(12)->timestamp])
            ->get(route('orders.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You were signed out after 10 minutes without activity.');

        $this->assertGuest();
    }

    public function test_a_staff_session_used_within_the_limit_carries_on(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->withSession([EnforceIdleTimeout::SESSION_KEY => now()->subMinutes(9)->timestamp])
            ->get(route('orders.index'))
            ->assertOk();

        $this->assertAuthenticatedAs($staff);
        $this->assertSame(now()->timestamp, session(EnforceIdleTimeout::SESSION_KEY));
    }

    public function test_the_background_heartbeat_only_counts_when_someone_actually_did_something(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $nineMinutesAgo = now()->subMinutes(9)->timestamp;

        $this->actingAs($staff)
            ->withSession([EnforceIdleTimeout::SESSION_KEY => $nineMinutesAgo])
            ->post(route('heartbeat'))
            ->assertNoContent();
        $this->assertSame($nineMinutesAgo, session(EnforceIdleTimeout::SESSION_KEY), 'A plain ping is not activity.');

        $this->post(route('heartbeat'), ['active' => 1])->assertNoContent();
        $this->assertSame(now()->timestamp, session(EnforceIdleTimeout::SESSION_KEY));
    }

    public function test_a_superadmin_is_not_timed_out(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($superadmin)
            ->withSession([EnforceIdleTimeout::SESSION_KEY => now()->subHours(3)->timestamp])
            ->get(route('superadmin.dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($superadmin);
    }

    public function test_only_pin_users_get_the_browser_idle_timer_and_the_kitchen_display_opts_out(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($staff)->get(route('orders.index'))
            ->assertSee('name="idle-timeout"', false)
            ->assertSee('content="600"', false);

        $this->actingAs($staff)->get(route('kitchen.index'))
            ->assertSee('data-idle-exempt', false);

        $this->actingAs($superadmin)->get(route('orders.index'))
            ->assertDontSee('name="idle-timeout"', false);
    }
}
