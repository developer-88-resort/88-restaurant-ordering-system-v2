<?php

namespace App\Support;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\OrderPayment;
use App\Models\Room;

/**
 * What the checkout form's room picker needs, from our own data only: the
 * active rooms grouped by type in front desk order, how many room charges
 * each room already has today (so the cashier double-checks the room), and
 * the guest name last used on each room in the past 24 hours (offered, never
 * forced).
 */
class RoomChargePicker
{
    /**
     * @return array{groups: list<array{code: string, name: string, rooms: list<array{id: int, no: string, label: string, typeName: string}>}>, chargedToday: array<int, int>, lastGuest: array<int, string>}
     */
    public static function config(): array
    {
        $rooms = Room::active()->inFrontDeskOrder()->with('roomType')->get();

        $groups = $rooms
            ->groupBy(fn (Room $room) => $room->roomType->code)
            ->map(fn ($rooms, $code) => [
                'code' => $code,
                'name' => $rooms->first()->roomType->name,
                'rooms' => $rooms->map(fn (Room $room) => [
                    'id' => $room->id,
                    'no' => $room->room_no,
                    'label' => $room->label(),
                    'typeName' => $room->roomType->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $recent = OrderPayment::query()
            ->where('payment_method', PaymentMethod::RoomCharge->value)
            ->where('status', OrderPaymentStatus::Recorded->value)
            ->whereNotNull('room_id')
            ->where('received_at', '>=', now()->subDay())
            ->orderBy('received_at')
            ->get(['room_id', 'guest_name', 'received_at']);

        $chargedToday = $recent
            ->filter(fn (OrderPayment $payment) => $payment->received_at->isToday())
            ->countBy('room_id')
            ->all();

        // Latest wins: the rows are oldest-first.
        $lastGuest = $recent
            ->filter(fn (OrderPayment $payment) => filled($payment->guest_name))
            ->mapWithKeys(fn (OrderPayment $payment) => [$payment->room_id => $payment->guest_name])
            ->all();

        return [
            'groups' => $groups,
            'chargedToday' => (object) $chargedToday,
            'lastGuest' => (object) $lastGuest,
        ];
    }
}
