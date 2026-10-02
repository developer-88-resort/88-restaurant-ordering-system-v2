<?php

namespace App\Enums;

enum OnlinePaymentStatus: string
{
    // Checkout created, guest hasn't finished paying yet.
    case Pending = 'pending';
    // Provider confirmed and the bill was finalized.
    case Paid = 'paid';
    // Provider confirmed the money, but the bill couldn't be finalized as
    // chosen (e.g. lines were added after checkout started) — a person has
    // to settle it; the money is real.
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for payment'),
            self::Paid => __('Paid'),
            self::NeedsReview => __('Paid — needs review'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * Maya's payment status, mapped onto ours. Anything still in flight
     * (PENDING_TOKEN, PENDING_PAYMENT, AUTH_SUCCESS...) stays Pending.
     */
    public static function fromMaya(string $status): self
    {
        return match ($status) {
            'PAYMENT_SUCCESS' => self::Paid,
            'PAYMENT_FAILED', 'AUTH_FAILED', 'PAYMENT_PROCESSING_FAILED' => self::Failed,
            'PAYMENT_EXPIRED', 'EXPIRED' => self::Expired,
            'PAYMENT_CANCELLED', 'VOIDED' => self::Cancelled,
            default => self::Pending,
        };
    }
}
