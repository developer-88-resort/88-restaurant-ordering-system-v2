<?php

namespace App\Support;

/**
 * The verdict on one keyed amount: how far it sits from what the reference
 * rate implies, and what that costs the person keying it in.
 *
 * Deliberately a value object with no behaviour beyond describing itself.
 * The same instance answers the server's "do I reject this?" and the
 * tablet's live "Expected ₱177.00 ✓" indicator, which is the point — the
 * screen cannot promise something the validator will then refuse.
 */
readonly class WeighVarianceResult
{
    public function __construct(
        public string $computedAmount,
        public string $amountCharged,
        public string $tolerance,
        /** Signed: positive means the customer was charged MORE than the rate implies. */
        public string $varianceAmount,
        public string $variancePercent,
        public string $hardCeilingPercent,
        public bool $requiresReason,
        public bool $requiresOverride,
    ) {}

    /** Inside tolerance: nothing to explain, nothing to approve. */
    public function passes(): bool
    {
        return ! $this->requiresReason && ! $this->requiresOverride;
    }

    public function absoluteVariance(): string
    {
        return ltrim($this->varianceAmount, '-');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passes' => $this->passes(),
            'computed_amount' => $this->computedAmount,
            'amount_charged' => $this->amountCharged,
            'tolerance' => $this->tolerance,
            'variance_amount' => $this->varianceAmount,
            'variance_percent' => $this->variancePercent,
            'hard_ceiling_percent' => $this->hardCeilingPercent,
            'requires_reason' => $this->requiresReason,
            'requires_override' => $this->requiresOverride,
        ];
    }
}
