<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Gcash = 'gcash';
    case Maya = 'maya';
    case BankTransfer = 'bank_transfer';
    case RoomCharge = 'room_charge';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::Gcash => __('GCash'),
            self::Maya => __('Maya'),
            // Stored as bank_transfer so older payments keep their method;
            // the resort takes these as a QR scan, so that's what it reads.
            self::BankTransfer => __('QR'),
            self::RoomCharge => __('Room Charge'),
            self::Other => __('Other'),
        };
    }

    /**
     * Cash is the only method where "amount tendered" naturally differs
     * from the total (making change) — every other method is assumed to
     * be paid exactly, and a reference number is required instead.
     */
    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }

    /**
     * What a Room Charge can be paid off through at the front desk: every
     * method except Room Charge itself.
     *
     * @return array<int, self>
     */
    public static function settlementOptions(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => $method !== self::RoomCharge));
    }
}
