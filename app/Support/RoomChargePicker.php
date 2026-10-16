<?php

namespace App\Support;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\MassagePayment;
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

        // Restaurant and Massage room charges alike — both go on the same rooms.
        $recentOf = fn (string $model) => $model::query()
            ->where('payment_method', PaymentMethod::RoomCharge->value)
            ->where('status', OrderPaymentStatus::Recorded->value)
            ->whereNotNull('room_id')
            ->where('received_at', '>=', now()->subDay())
            ->get(['room_id', 'guest_name', 'received_at']);

        $recent = $recentOf(OrderPayment::class)
            ->concat($recentOf(MassagePayment::class))
            ->sortBy('received_at')
            ->values();

        $chargedToday = $recent
            ->filter(fn ($payment) => $payment->received_at->isToday())
            ->countBy('room_id')
            ->all();

        // Latest wins: the rows are oldest-first.
        $lastGuest = $recent
            ->filter(fn ($payment) => filled($payment->guest_name))
            ->mapWithKeys(fn ($payment) => [$payment->room_id => $payment->guest_name])
            ->all();

        return [
            'groups' => $groups,
            'chargedToday' => (object) $chargedToday,
            'lastGuest' => (object) $lastGuest,
            'spots' => self::spots(),
        ];
    }

    /**
     * Locker Charge and Kubo Charge: every locker / kubo by number, in
     * groups of ten, with the same "charged today" count and last guest
     * (past 24 hours) as a room — keyed by method, then number.
     *
     * @return array<string, array{name: string, count: int, groups: list<array{label: string, nos: list<int>}>, chargedToday: object, lastGuest: object}>
     */
    protected static function spots(): array
    {
        $recentOf = fn (string $model) => $model::query()
            ->whereIn('payment_method', array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::spotMethods()))
            ->where('status', OrderPaymentStatus::Recorded->value)
            ->whereNotNull('spot_no')
            ->where('received_at', '>=', now()->subDay())
            ->get(['payment_method', 'spot_no', 'guest_name', 'received_at']);

        $recent = $recentOf(OrderPayment::class)
            ->concat($recentOf(MassagePayment::class))
            ->sortBy('received_at')
            ->values();

        return collect(PaymentMethod::spotMethods())->mapWithKeys(function (PaymentMethod $method) use ($recent) {
            $mine = $recent->filter(fn ($payment) => $payment->payment_method === $method);

            return [$method->value => [
                'name' => $method->spotName(),
                'count' => $method->spotCount(),
                'groups' => collect(range(1, $method->spotCount()))
                    ->chunk(10)
                    ->map(fn ($nos) => ['label' => $nos->first().'–'.$nos->last(), 'nos' => $nos->values()->all()])
                    ->values()
                    ->all(),
                'chargedToday' => (object) $mine->filter(fn ($payment) => $payment->received_at->isToday())->countBy('spot_no')->all(),
                'lastGuest' => (object) $mine->filter(fn ($payment) => filled($payment->guest_name))
                    ->mapWithKeys(fn ($payment) => [$payment->spot_no => $payment->guest_name])
                    ->all(),
            ]];
        })->all();
    }
}
