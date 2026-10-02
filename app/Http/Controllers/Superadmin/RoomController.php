<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin → Rooms: the guest rooms a Room Charge can go on, numbered and
 * typed the way the front desk system shows them. Rooms are added, re-typed,
 * re-ordered or switched off here — never deleted, since past room charges
 * point at them — so a room change never needs a deploy.
 */
class RoomController extends Controller
{
    public function index(): View
    {
        $types = RoomType::orderBy('sort_order')->get();
        $rooms = Room::inFrontDeskOrder()->with('roomType')->get()->groupBy('room_type_id');

        return view('superadmin.rooms.index', [
            'types' => $types,
            'roomsByType' => $rooms,
            'frontDeskCopy' => Setting::current()->room_charge_front_desk_copy,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('createRoom', [
            'room_no' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('rooms', 'room_no')],
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $room = Room::create([
            'room_no' => strtoupper(trim($data['room_no'])),
            'room_type_id' => $data['room_type_id'],
            // Without an order of its own, a new room goes after the others.
            'sort_order' => $data['sort_order'] ?? ((int) Room::max('sort_order') + 10),
            'active' => true,
        ]);

        return redirect()->route('superadmin.rooms.index')
            ->with('status', __('Room :room added.', ['room' => $room->load('roomType')->label()]));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => ['sometimes', 'integer', Rule::exists('room_types', 'id')],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $room->update($data);
        $label = $room->load('roomType')->label();

        $message = match (true) {
            array_key_exists('active', $data) && ! $room->active => __('Room :room switched off — it no longer shows in the room picker.', ['room' => $label]),
            array_key_exists('active', $data) => __('Room :room switched back on.', ['room' => $label]),
            default => __('Room :room updated.', ['room' => $label]),
        };

        return redirect()->route('superadmin.rooms.index')->with('status', $message);
    }

    /**
     * Whether a room charge receipt prints its authorization block a second
     * time as the front desk's copy.
     */
    public function updateFrontDeskCopy(Request $request): RedirectResponse
    {
        $data = $request->validate(['room_charge_front_desk_copy' => ['required', 'boolean']]);

        Setting::current()->update($data);

        return redirect()->route('superadmin.rooms.index')->with('status', $data['room_charge_front_desk_copy']
            ? __('Room charge receipts will print a front desk copy.')
            : __('Room charge receipts will print one copy only.'));
    }
}
