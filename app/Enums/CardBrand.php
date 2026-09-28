<?php

namespace App\Enums;

/**
 * The card types the checkout offers for a card payment. The value is the
 * name as printed on the card machine receipt, and is what's stored in
 * order_payments.card_brand — the same free-text column older payments
 * filled by hand, so a receipt reads the same either way.
 */
enum CardBrand: string
{
    case Visa = 'Visa';
    case Mastercard = 'Mastercard';
    case BancNet = 'BancNet';
    case Jcb = 'JCB';
    case AmericanExpress = 'American Express';
    case UnionPay = 'UnionPay';
    case DinersClub = 'Diners Club';
    case Other = 'Other';

    public function label(): string
    {
        return $this === self::Other ? __('Other card') : $this->value;
    }
}
