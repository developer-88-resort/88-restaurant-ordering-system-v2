<?php

namespace Tests\Feature;

use App\Enums\PromotionEventType;
use App\Models\Promotion;
use App\Models\PromotionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_active_banner_records_an_impression(): void
    {
        $promotion = $this->activePromotion();

        $this->post(route('customer.promotions.view', $promotion))->assertNoContent();

        $event = PromotionEvent::firstOrFail();
        $this->assertSame($promotion->id, $event->promotion_id);
        $this->assertSame(PromotionEventType::View, $event->event_type);
        $this->assertNotNull($event->session_id);
    }

    public function test_repeated_views_in_the_same_session_are_not_double_counted(): void
    {
        // Recorder-level, not HTTP round-trips: phpunit.xml forces
        // SESSION_DRIVER=array for tests, so $request->session()->getId()
        // isn't stable across separate test requests the way it is for a
        // real browser (confirmed live: SESSION_DRIVER=database in
        // production, same session cookie survives a refresh).
        $promotion = $this->activePromotion();
        $recorder = app(\App\Support\PromotionEventRecorder::class);

        $recorder->recordView($promotion, 'same-session');
        $recorder->recordView($promotion, 'same-session');
        $recorder->recordView($promotion, 'same-session');

        $this->assertSame(1, PromotionEvent::where('promotion_id', $promotion->id)->where('event_type', 'view')->count());
    }

    public function test_a_different_session_still_counts_as_a_new_view(): void
    {
        $promotion = $this->activePromotion();
        $recorder = app(\App\Support\PromotionEventRecorder::class);

        $recorder->recordView($promotion, 'session-one');
        $recorder->recordView($promotion, 'session-two');

        $this->assertSame(2, PromotionEvent::where('promotion_id', $promotion->id)->where('event_type', 'view')->count());
    }

    public function test_banner_cta_records_a_click_and_redirects_to_its_target(): void
    {
        $promotion = $this->activePromotion();

        $this->get(route('customer.promotions.click', $promotion))
            ->assertRedirect('https://example.com/weekend-special');

        $this->assertDatabaseHas('promotion_events', [
            'promotion_id' => $promotion->id,
            'event_type' => PromotionEventType::Click->value,
        ]);
    }

    public function test_inactive_banner_cannot_record_or_redirect(): void
    {
        $promotion = $this->activePromotion([
            'starts_at' => now()->addDay(),
        ]);

        $this->post(route('customer.promotions.view', $promotion))->assertNotFound();
        $this->get(route('customer.promotions.click', $promotion))->assertNotFound();
        $this->assertDatabaseCount('promotion_events', 0);
    }

    private function activePromotion(array $attributes = []): Promotion
    {
        return Promotion::create($attributes + [
            'image_path' => 'promotions/weekend.jpg',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_published' => true,
            'banner_cta_url' => 'https://example.com/weekend-special',
        ]);
    }
}
