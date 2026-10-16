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
    case LockerCharge = 'locker_charge';
    case KuboCharge = 'kubo_charge';
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
            self::LockerCharge => __('Locker Charge'),
            self::KuboCharge => __('Kubo Charge'),
            self::Other => __('Others'),
        };
    }

    /**
     * Cash is the only method where "amount tendered" naturally differs
     * from the total (making change) — every other method is assumed to
     * be paid exactly, and a reference number is required instead. A
     * charge (room, locker, kubo) collects nothing here, so it has none —
     * nor does Others, which is now only a breakfast order's complimentary.
     */
    public function requiresReference(): bool
    {
        return $this !== self::Cash && $this !== self::Other && ! $this->isChargeToAccount();
    }

    /**
     * Charged now, settled later at the front desk: nothing is collected at
     * the outlet, so these stay out of the drawer totals and print a signed
     * authorization on the receipt. A room is picked from `rooms`; a locker
     * or a kubo by its number (see spotCount()).
     */
    public function isChargeToAccount(): bool
    {
        return in_array($this, [self::RoomCharge, self::LockerCharge, self::KuboCharge], true);
    }

    /**
     * Charged to a numbered locker or kubo rather than a room.
     *
     * @return array<int, self>
     */
    public static function spotMethods(): array
    {
        return [self::LockerCharge, self::KuboCharge];
    }

    public function isSpotCharge(): bool
    {
        return in_array($this, self::spotMethods(), true);
    }

    /** "Locker", "Kubo" — what one numbered spot is called; null for any other method. */
    public function spotName(): ?string
    {
        return match ($this) {
            self::LockerCharge => __('Locker'),
            self::KuboCharge => __('Kubo'),
            default => null,
        };
    }

    /** How many lockers / kubos there are, numbered from 1. */
    public function spotCount(): int
    {
        return match ($this) {
            self::LockerCharge => 50,
            self::KuboCharge => 20,
            default => 0,
        };
    }

    /** "Locker 12", "Kubo 7". */
    public function spotLabel(int|string $no): string
    {
        return $this->spotName().' '.$no;
    }

    /**
     * What a Room Charge can be paid off through at the front desk: every
     * method except the charges themselves.
     *
     * @return array<int, self>
     */
    public static function settlementOptions(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => ! $method->isChargeToAccount()));
    }

    /**
     * What a breakfast order can be settled with: Others only — the whole
     * order is complimentary (App\Support\Breakfast).
     *
     * @return array<int, self>
     */
    public static function complimentaryOnly(): array
    {
        return [self::Other];
    }

    /**
     * What a cashier can pick for a new payment. "Other" is no longer
     * offered — every payment names its real method — but stays a case so
     * the payments already recorded as Other keep reading and reporting.
     * A breakfast order offers only Others instead (complimentaryOnly()).
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method) => $method !== self::Other));
    }
}
