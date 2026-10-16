<?php

namespace App\Enums;

enum DiscountType: string
{
    case SeniorCitizen = 'senior_citizen';
    case Pwd = 'pwd';
    // Diplomats are VAT-exempt only — no percentage off on top. Their share
    // of a group bill is the bill split per person, times how many of the
    // group are diplomats (see the 'per_person' discount-rule scope).
    case Diplomat = 'diplomat';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::SeniorCitizen => __('Senior Citizen'),
            self::Pwd => __('PWD'),
            self::Diplomat => __('Diplomat'),
            self::Promo => __('Regular/Promotional Discount'),
        };
    }

    /**
     * Only statutory discounts (Senior/PWD/Diplomat) get the VAT-exemption
     * treatment — Senior/PWD under RA 9994/RA 10754, diplomats as VAT-exempt
     * buyers. A promo is just a plain percentage off, still fully VATable.
     */
    public function isStatutory(): bool
    {
        return $this !== self::Promo;
    }
}
