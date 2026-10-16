<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use App\Models\Room;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The room side of a payment entry: a Room Charge goes on a room picked from
 * `rooms`, with its number and type code snapshotted (room_no,
 * room_type_code) and, optionally, who signed for it (guest_name,
 * guest_ref — shown as "OS"). A Locker or Kubo Charge goes on a numbered
 * locker or kubo instead (spot_no). Shared by the restaurant's order_payments and Massage's
 * massage_payments, which both take payment through the same payment rows
 * and PaymentFinalizer::validatePayments().
 */
trait RecordsRoomCharge
{
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isRoomCharge(): bool
    {
        return $this->payment_method === PaymentMethod::RoomCharge;
    }

    /**
     * A room charge from before rooms were picked from a list: only the
     * free text typed into `charged_to` says where it went.
     */
    public function isLegacyRoomCharge(): bool
    {
        return $this->isRoomCharge() && $this->room_no === null;
    }

    /** "511 PH", from the snapshot taken at checkout; null for a legacy charge. */
    public function roomLabel(): ?string
    {
        return $this->room_no !== null ? Room::formatLabel($this->room_no, $this->room_type_code) : null;
    }

    /** A Locker or Kubo Charge. */
    public function isSpotCharge(): bool
    {
        return $this->payment_method?->isSpotCharge() ?? false;
    }

    /** "Locker 12", "Kubo 7"; null for anything not charged to a locker or kubo. */
    public function spotLabel(): ?string
    {
        return $this->isSpotCharge() && $this->spot_no !== null
            ? $this->payment_method->spotLabel($this->spot_no)
            : null;
    }

    /**
     * Where a charge goes, as the receipt's authorization prints it:
     * "ROOM 511 PH", "LOCKER 12", "KUBO 7" — or an older charge's free text.
     */
    public function chargeTargetLabel(): string
    {
        if ($this->roomLabel() !== null) {
            return __('ROOM').' '.$this->roomLabel();
        }

        if ($this->spotLabel() !== null) {
            return mb_strtoupper($this->spotLabel());
        }

        return $this->charged_to ?: '—';
    }

    /** What `charged_to` says for a picked locker or kubo: "LOCKER 12 — Juan". */
    public static function spotChargedToFor(string $spotLabel, ?string $guestName): string
    {
        $guestName = trim((string) $guestName);

        return mb_strtoupper($spotLabel).($guestName !== '' ? ' — '.$guestName : '');
    }

    /** The type's full name ("Pension House"), while the room still exists. */
    public function roomTypeName(): ?string
    {
        return $this->room?->roomType?->name;
    }

    /**
     * How a room charge gets paid: nothing is collected at the outlet — the
     * guest settles the whole folio at front desk checkout. Older charges
     * kept the "Paid through" mode staff had to pick (almost always Other).
     */
    public function settlementLabel(): string
    {
        return $this->settled_via?->label() ?? __('To be settled at front desk');
    }

    /**
     * What `charged_to` says for a picked room — kept written so anything
     * that reads only that column still shows the room: "RM 511 PH — Juan".
     */
    public static function chargedToFor(string $roomLabel, ?string $guestName): string
    {
        $guestName = trim((string) $guestName);

        return 'RM '.$roomLabel.($guestName !== '' ? ' — '.$guestName : '');
    }
}
