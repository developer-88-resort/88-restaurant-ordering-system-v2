<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'is_active' => true]);

        Storage::fake('public');
    }

    public function test_staff_cannot_access_promotions(): void
    {
        $this->actingAs($this->staff)->get('/superadmin/promotions')->assertForbidden();
    }

    public function test_admin_can_create_a_banner(): void
    {
        $file = UploadedFile::fake()->image('banner.jpg');
        $mobileFile = UploadedFile::fake()->image('mobile-banner.jpg', 1200, 400);

        $response = $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'image' => $file,
            'mobile_image' => $mobileFile,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'banner_cta_url' => 'https://example.com',
        ]);

        $response->assertRedirect(route('superadmin.promotions.index'));

        $promotion = Promotion::firstOrFail();
        $this->assertStringStartsWith('PRM-', $promotion->code);
        $this->assertSame($this->admin->id, $promotion->created_by);
        $this->assertSame('https://example.com', $promotion->banner_cta_url);
        Storage::disk('public')->assertExists($promotion->image_path);
        Storage::disk('public')->assertExists($promotion->mobile_image_path);
    }

    public function test_image_is_required_on_create(): void
    {
        $response = $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Promotion::count());
    }

    public function test_starts_at_is_optional_and_the_banner_goes_live_immediately(): void
    {
        $response = $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'image' => UploadedFile::fake()->image('banner.jpg'),
            'is_published' => true,
        ]);

        $response->assertRedirect(route('superadmin.promotions.index'));

        $promotion = Promotion::firstOrFail();
        $this->assertNull($promotion->starts_at);
        $this->assertSame('active', $promotion->status->value);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'image' => UploadedFile::fake()->image('banner.jpg'),
            'starts_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('ends_at');
        $this->assertSame(0, Promotion::count());
    }

    /**
     * @return array<int, array{0: string, 1: bool, 2: bool, 3: ?string, 4: ?string}>
     */
    public static function statusCases(): array
    {
        return [
            'draft when unpublished' => ['draft', false, false, 'now', null],
            'scheduled when starts in the future' => ['scheduled', true, false, '+3 days', null],
            'active within an open window' => ['active', true, false, '-1 day', null],
            'active within a closed window' => ['active', true, false, '-1 day', '+1 day'],
            'active immediately when starts_at is null' => ['active', true, false, null, null],
            'expired once ends_at has passed' => ['expired', true, false, '-10 days', '-1 day'],
            'disabled overrides an otherwise-active window' => ['disabled', true, true, '-1 day', '+1 day'],
        ];
    }

    #[DataProvider('statusCases')]
    public function test_status_is_computed_correctly(string $expected, bool $isPublished, bool $isDisabled, ?string $startsAtModifier, ?string $endsAtModifier): void
    {
        $promotion = Promotion::create([
            'starts_at' => $startsAtModifier ? now()->modify($startsAtModifier) : null,
            'ends_at' => $endsAtModifier ? now()->modify($endsAtModifier) : null,
            'is_published' => $isPublished,
            'is_disabled' => $isDisabled,
        ]);

        $this->assertSame($expected, $promotion->status->value);
    }

    public function test_update_never_resets_code_or_created_at(): void
    {
        $promotion = Promotion::create([
            'starts_at' => now(),
            'image_path' => 'promotions/original.jpg',
        ]);
        $originalCode = $promotion->code;
        $originalCreatedAt = $promotion->created_at;

        $this->actingAs($this->admin)->put("/superadmin/promotions/{$promotion->id}", [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'is_published' => true,
        ])->assertRedirect();

        $promotion->refresh();
        $this->assertTrue($promotion->is_published);
        $this->assertSame($originalCode, $promotion->code);
        $this->assertSame($originalCreatedAt->timestamp, $promotion->created_at->timestamp);
    }

    public function test_update_preserves_legacy_title_and_description(): void
    {
        $promotion = Promotion::create(['starts_at' => now(), 'image_path' => 'promotions/original.jpg']);
        $promotion->forceFill(['title' => 'Legacy Campaign', 'description' => 'Legacy details'])->saveQuietly();

        $this->actingAs($this->admin)->put("/superadmin/promotions/{$promotion->id}", [
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $promotion->refresh();
        $this->assertSame('Legacy Campaign', $promotion->title);
        $this->assertSame('Legacy details', $promotion->description);
    }

    public function test_image_upload_is_stored_and_replaced_correctly(): void
    {
        $file = UploadedFile::fake()->image('banner.jpg');

        $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'image' => $file,
        ])->assertRedirect();

        $promotion = Promotion::firstOrFail();
        Storage::disk('public')->assertExists($promotion->image_path);
        $firstPath = $promotion->image_path;

        $newFile = UploadedFile::fake()->image('new-banner.jpg');
        $this->actingAs($this->admin)->put("/superadmin/promotions/{$promotion->id}", [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'image' => $newFile,
        ]);

        $promotion->refresh();
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($promotion->image_path);
    }

    public function test_update_requires_an_image_when_removing_without_a_replacement(): void
    {
        $promotion = Promotion::create(['starts_at' => now(), 'image_path' => 'promotions/existing.jpg']);

        $response = $this->actingAs($this->admin)->put("/superadmin/promotions/{$promotion->id}", [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'remove_image' => true,
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame('promotions/existing.jpg', $promotion->fresh()->image_path);
    }

    public function test_destroy_removes_the_row_and_its_stored_image(): void
    {
        $file = UploadedFile::fake()->image('banner.jpg');
        $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'image' => $file,
        ]);

        $promotion = Promotion::firstOrFail();
        $imagePath = $promotion->image_path;

        $this->actingAs($this->admin)->delete("/superadmin/promotions/{$promotion->id}")->assertRedirect();

        $this->assertSame(0, Promotion::count());
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_toggle_status_flips_is_disabled(): void
    {
        $promotion = Promotion::create(['starts_at' => now(), 'is_disabled' => false]);

        $this->actingAs($this->admin)->post("/superadmin/promotions/{$promotion->id}/toggle-status")->assertRedirect();
        $this->assertTrue($promotion->fresh()->is_disabled);

        $this->actingAs($this->admin)->post("/superadmin/promotions/{$promotion->id}/toggle-status")->assertRedirect();
        $this->assertFalse($promotion->fresh()->is_disabled);
    }

    public function test_creating_a_banner_produces_an_audit_log_entry_with_a_fallback_identifier(): void
    {
        $this->actingAs($this->admin)->post('/superadmin/promotions', [
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'image' => UploadedFile::fake()->image('banner.jpg'),
        ]);

        $promotion = Promotion::firstOrFail();
        $activity = Activity::where('subject_type', Promotion::class)->latest()->firstOrFail();
        $this->assertSame('Created Promotion: Banner #'.$promotion->id, $activity->description);
    }
}
