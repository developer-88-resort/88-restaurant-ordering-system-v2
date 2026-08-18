<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'is_active' => true]);
    }

    private function log(array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'log_name' => 'audit',
            'description' => 'A test log entry.',
            'event' => 'updated',
        ], $overrides));
    }

    public function test_filtering_by_event_narrows_results(): void
    {
        $this->log(['event' => 'weigh_in_voided', 'description' => 'WEIGH-IN VOIDED: Bangus']);
        $this->log(['event' => 'created', 'description' => 'Menu Item Created: Bangus']);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['events' => ['weigh_in_voided']]))
            ->assertOk()
            ->assertSee('WEIGH-IN VOIDED: Bangus')
            ->assertDontSee('Menu Item Created: Bangus');
    }

    public function test_searching_details_narrows_results(): void
    {
        $this->log(['description' => 'WEIGH-IN RECORDED: Bangus — 1000 g']);
        $this->log(['description' => 'Settings Updated']);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['search' => 'Bangus']))
            ->assertOk()
            ->assertSee('WEIGH-IN RECORDED: Bangus')
            ->assertDontSee('Settings Updated');
    }

    public function test_filtering_by_user_narrows_results(): void
    {
        $other = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        $this->log(['description' => 'Entry by superadmin', 'causer_id' => $this->superadmin->id, 'causer_type' => User::class]);
        $this->log(['description' => 'Entry by other user', 'causer_id' => $other->id, 'causer_type' => User::class]);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['user_id' => $other->id]))
            ->assertOk()
            ->assertSee('Entry by other user')
            ->assertDontSee('Entry by superadmin');
    }

    public function test_filtering_by_date_range_narrows_results(): void
    {
        $this->log(['description' => 'Recent entry']);
        $old = $this->log(['description' => 'Old entry']);
        DB::table('activity_log')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['from' => now()->subDays(2)->toDateString()]))
            ->assertOk()
            ->assertSee('Recent entry')
            ->assertDontSee('Old entry');
    }

    public function test_a_filter_stays_applied_when_paging_to_a_second_page(): void
    {
        // 25 matching entries (page size is 20) plus 5 that must never
        // surface, so paging to page 2 while filtered proves the filter —
        // not just the page number — survived the navigation.
        for ($i = 0; $i < 25; $i++) {
            $this->log(['description' => "Marker entry {$i}"]);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->log(['description' => "Unrelated entry {$i}"]);
        }

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['search' => 'Marker', 'page' => 2]))
            ->assertOk()
            ->assertSee('Marker entry')
            ->assertDontSee('Unrelated entry');
    }

    /**
     * A plain "created" event is not unique to one model — every audited
     * model logs it. The filter option is qualified with subject_type
     * ("event:FqcnOfSubject") so selecting one badge's filter only pulls
     * that model's rows, not every "created" row app-wide.
     */
    public function test_filtering_by_a_model_qualified_action_only_matches_that_model(): void
    {
        $this->log(['event' => 'created', 'subject_type' => 'App\\Models\\MenuItem', 'description' => 'Menu item row']);
        $this->log(['event' => 'created', 'subject_type' => 'App\\Models\\Setting', 'description' => 'Setting row']);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['events' => ['created:App\\Models\\MenuItem']]))
            ->assertOk()
            ->assertSee('Menu item row')
            ->assertDontSee('Setting row');
    }

    /** Every option label — auth events, custom weigh events, and generic CRUD — goes through one humanizer. */
    public function test_custom_event_labels_are_humanized_consistently(): void
    {
        $this->log(['event' => 'advance_order_added', 'description' => 'Advance order line added']);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Advance Order Added')
            ->assertDontSee('Advance_order_added');
    }

    /** Model-specific badges read as a real name, not the raw PHP class name. */
    public function test_badges_humanize_the_underlying_model_name(): void
    {
        $this->log(['event' => 'created', 'subject_type' => 'App\\Models\\OrderInvoiceSnapshot', 'description' => 'Invoice snapshot row']);
        $this->log(['event' => 'created', 'subject_type' => 'App\\Models\\OrderItemAdjustment', 'description' => 'Item adjustment row']);

        $response = $this->actingAs($this->superadmin)->get(route('superadmin.audit-logs.index'));

        // Note: the raw FQCN still appears once as a hidden filter
        // checkbox's `value=""` attribute — that's a form control value,
        // not user-visible text, so it's not asserted against here; the
        // badge/label TEXT is what must read humanized.
        $response->assertOk();
        $response->assertSee('Invoice Snapshot · Created', false);
        $response->assertSee('Item Adjustment · Created', false);
    }

    public function test_empty_filter_params_are_dropped_from_the_form_action_state(): void
    {
        // The form strips empty fields client-side before submit (JS) — a
        // server-side regression check here just confirms an empty events[]
        // input never accidentally becomes a real "match nothing" filter.
        $this->log(['description' => 'Visible entry']);

        $this->actingAs($this->superadmin)
            ->get(route('superadmin.audit-logs.index', ['events' => [], 'search' => '', 'user_id' => '', 'from' => '', 'to' => '']))
            ->assertOk()
            ->assertSee('Visible entry');
    }
}
