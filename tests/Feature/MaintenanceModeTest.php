<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\UserRole;
use App\Enums\IncidentStatus;
use App\Events\MaintenanceModeChanged;
use Illuminate\Support\Facades\Event;
use App\Models\Setting;
use App\Models\StatusIncident;
use App\Support\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Maintenance Mode (Settings): Superadmin switches it on, and from then on
 * every Admin, Staff and customer gets the maintenance page until it is
 * switched off. Signing in stays open so a Superadmin can still get in.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boss = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'department' => Department::Restaurant, 'is_active' => true]);
        $this->cashier = User::factory()->create(['role' => UserRole::Staff, 'department' => Department::Restaurant, 'is_active' => true]);
    }

    private function switchMaintenance(bool $on, ?string $message = null)
    {
        return $this->actingAs($this->boss)->put(route('superadmin.settings.maintenance'), [
            'maintenance_mode' => $on ? 1 : 0,
            'maintenance_message' => $message,
            'incident_title' => 'System update',
            'incident_impact' => 'maintenance',
            'incident_components' => ['ordering', 'customer'],
        ]);
    }

    public function test_only_a_superadmin_can_switch_it(): void
    {
        $this->actingAs($this->admin)->put(route('superadmin.settings.maintenance'), ['maintenance_mode' => 1])->assertForbidden();
        $this->assertFalse(Setting::current()->maintenance_mode);

        $this->switchMaintenance(true, 'Back at 3 PM.')->assertRedirect(route('superadmin.settings.edit'));
        $this->assertTrue(Setting::current()->fresh()->maintenance_mode);
        $this->assertSame('Back at 3 PM.', Setting::current()->fresh()->maintenance_message);
    }

    public function test_everyone_but_superadmin_sees_the_maintenance_page_while_it_is_on(): void
    {
        $this->actingAs($this->cashier)->get(route('orders.index'))->assertOk();

        $this->switchMaintenance(true, 'Back at 3 PM.');

        foreach ([$this->admin, $this->cashier] as $user) {
            $this->actingAs($user)->get(route('orders.index'))
                ->assertStatus(503)
                ->assertSee('Website under maintenance')
                ->assertSee('Back at 3 PM.');
        }

        // Customers at a table QR, and anyone signed out.
        auth()->logout();
        $this->get(route('customer.orders.status', 'any-token'))->assertStatus(503)->assertSee('Website under maintenance');

        // The Superadmin carries on, with a reminder that it is on.
        $this->actingAs($this->boss)->get(route('orders.index'))->assertOk()->assertSee('Maintenance Mode is on');
        $this->actingAs($this->boss)->get(route('superadmin.dashboard'))->assertOk();
    }

    public function test_the_sign_in_page_stays_reachable_for_a_superadmin(): void
    {
        $this->switchMaintenance(true);
        auth()->logout();

        $this->get(route('login'))->assertStatus(503)->assertSee('Website under maintenance')->assertSee('Superadmin sign-in');
        $this->get(route('login', ['signin' => 1]))->assertOk();

        $this->post('/login', ['email' => $this->boss->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($this->boss);
    }

    public function test_inertia_and_json_requests_get_a_full_page_load_or_a_503(): void
    {
        $this->switchMaintenance(true);

        $this->actingAs($this->admin)
            ->get(route('superadmin.dashboard'), ['X-Inertia' => 'true', 'X-Inertia-Version' => 'x'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');

        $this->actingAs($this->admin)->getJson(route('orders.index'))->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_switching_it_off_lets_everyone_back_in(): void
    {
        $this->switchMaintenance(true);
        $this->actingAs($this->cashier)->get(route('orders.index'))->assertStatus(503);

        $this->switchMaintenance(false);
        $this->actingAs($this->cashier)->get(route('orders.index'))->assertOk();
    }

    public function test_switching_on_opens_an_incident_updates_post_to_it_and_off_resolves_it(): void
    {
        $this->actingAs($this->boss)->put(route('superadmin.settings.maintenance'), ['maintenance_mode' => 1, 'incident_impact' => 'major'])
            ->assertSessionHasErrors('incident_components');
        $this->assertFalse(Setting::maintenanceIsOn());

        $this->switchMaintenance(true, 'Inaayos ang printer.');
        $incident = StatusIncident::sole();
        $this->assertNull($incident->resolved_at);
        $this->assertSame(['ordering', 'customer'], $incident->components);
        $this->assertSame(IncidentStatus::InProgress, $incident->updates->sole()->status);

        $this->actingAs($this->boss)->post(route('superadmin.settings.status.incidents.updates', $incident), ['status' => 'identified', 'message' => 'Nakita na ang sanhi.'])
            ->assertRedirect(route('superadmin.settings.edit'));
        // An Admin is held at the maintenance page before anything else runs.
        $this->actingAs($this->admin)->post(route('superadmin.settings.status.incidents.updates', $incident), ['status' => 'update', 'message' => 'x'])->assertStatus(503);
        $this->assertSame(2, $incident->updates()->count());

        auth()->logout();
        $this->get(route('login'))->assertStatus(503)
            ->assertSeeInOrder(['System update', 'Identified', 'Nakita na ang sanhi.', 'In progress', 'Inaayos ang printer.'])
            ->assertSee('Uptime over the past 90 days.')
            ->assertSee('Customer QR Ordering')
            ->assertDontSee('Past Incidents');

        $this->switchMaintenance(false, 'Okay na lahat.');
        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(IncidentStatus::Resolved, $incident->updates->first()->status);
        $this->assertSame('Okay na lahat.', $incident->updates->first()->message);
        $this->assertFalse(Setting::maintenanceIsOn());
    }

    public function test_uptime_bars_are_coloured_from_incidents_on_each_part(): void
    {
        StatusIncident::create([
            'title' => 'Printer down', 'impact' => 'major', 'components' => ['printing'],
            'started_at' => now()->subDays(2)->setTime(10, 0), 'resolved_at' => now()->subDays(2)->setTime(12, 0),
        ]);

        $rows = collect(StatusPage::build()['components'])->keyBy('key');
        $printing = $rows['printing'];
        $twoDaysAgo = $printing['days'][StatusPage::DAYS - 3];

        $this->assertSame('#E11D48', $twoDaysAgo['color']);
        $this->assertSame(120, $twoDaysAgo['downMinutes']);
        $this->assertSame(['Printer down'], $twoDaysAgo['incidents']);
        $this->assertLessThan(100, $printing['uptime']);
        $this->assertNull($printing['state']);

        $this->assertSame(100.0, (float) $rows['ordering']['uptime']);
        $this->assertSame('#22C55E', $rows['ordering']['days'][StatusPage::DAYS - 3]['color']);
    }

    public function test_the_status_page_is_open_to_anyone_at_any_time(): void
    {
        $this->get(route('status'))->assertOk()->assertSee('All systems operational')->assertSee('Uptime over the past 90 days.');

        $this->switchMaintenance(true, 'Babalik kami ng 3 PM.');
        auth()->logout();

        $this->get(route('status'))->assertOk()->assertSee('System update')->assertSee('Babalik kami ng 3 PM.')->assertDontSee('All systems operational');
        $this->get(route('login'))->assertStatus(503)->assertSee(route('status'));
    }


    public function test_switching_tells_every_open_screen_and_the_check_reports_it(): void
    {
        Event::fake([MaintenanceModeChanged::class]);

        $this->getJson(route('maintenance.check'))->assertOk()->assertExactJson(['on' => false]);

        $this->switchMaintenance(true);
        Event::assertDispatched(MaintenanceModeChanged::class, fn ($event) => $event->on === true);
        // Answered even for a signed-in Admin during maintenance (no page, no PIN check).
        $this->actingAs($this->admin)->getJson(route('maintenance.check'))->assertOk()->assertExactJson(['on' => true]);

        $this->switchMaintenance(false);
        Event::assertDispatched(MaintenanceModeChanged::class, fn ($event) => $event->on === false);
        $this->getJson(route('maintenance.check'))->assertExactJson(['on' => false]);
    }

    public function test_only_a_superadmin_screen_is_marked_exempt_from_the_reload(): void
    {
        $this->actingAs($this->boss)->get(route('orders.index'))->assertSee('name="maintenance-exempt" content="1"', false);
        $this->actingAs($this->cashier)->get(route('orders.index'))->assertSee('name="maintenance-exempt" content="0"', false);
    }
}
