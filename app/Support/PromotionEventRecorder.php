<?php

namespace App\Support;

use App\Enums\PromotionEventType;
use App\Models\Promotion;

/**
 * Backs the customer-facing banner carousel/rail
 * (resources/js/Pages/Customer/Menu/PromotionCarousel.jsx,
 * DesktopPromotionRail.jsx) via CustomerPromotionController.
 */
class PromotionEventRecorder
{
    /**
     * One view per (promotion, session) — without this, a page refresh
     * re-fires the client's impression ping and inflates the count every
     * time, since the browser keeps the same session cookie across
     * refreshes. A genuinely new visit (session expired/cleared) is a real
     * new impression and is allowed to count again.
     */
    public function recordView(Promotion $promotion, ?string $sessionId = null): void
    {
        if ($sessionId && $this->alreadyRecorded($promotion, PromotionEventType::View, $sessionId)) {
            return;
        }

        $promotion->events()->create([
            'event_type' => PromotionEventType::View,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Clicks are deliberately NOT deduplicated — each tap is a distinct,
     * intentional action, unlike a view that a mere refresh can re-trigger.
     */
    public function recordClick(Promotion $promotion, ?string $sessionId = null): void
    {
        $promotion->events()->create([
            'event_type' => PromotionEventType::Click,
            'session_id' => $sessionId,
        ]);
    }

    protected function alreadyRecorded(Promotion $promotion, PromotionEventType $type, string $sessionId): bool
    {
        return $promotion->events()
            ->where('event_type', $type)
            ->where('session_id', $sessionId)
            ->exists();
    }
}
