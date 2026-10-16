<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * The resort's rooms, copied once from the front desk system (WinCloud) so
 * staff see the same numbers and type codes on both sides. There is no
 * link to WinCloud — later changes go through Superadmin → Rooms.
 *
 * Order follows WinCloud's Room Status screen (VR → BD → STD → PH → BS →
 * GR → EXR). Idempotent: keyed on the type code and the room number, it
 * never deletes or deactivates a room added later in Superadmin → Rooms, and
 * never re-activates one switched off there.
 */
class RoomSeeder extends Seeder
{
    /**
     * code => [name, room numbers]. No 201 — WinCloud has none. PH is
     * "Pension House"; WinCloud's "Garden room" is normalized to "Garden Room".
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const TYPES = [
        'VR' => ['Villa Room', ['101', '102', '103', '104', '105', '106', '107', '108', '109', '110', '111', '112']],
        'BD' => ['Bamboo Deluxe', ['202', '203', '204', '205', '206']],
        'STD' => ['Standard Room', ['501', '502', '503', '504', '505', '506', '507', '508', '509', '510']],
        'PH' => ['Pension House', ['511', '512', '513', '514', '515', '516', '517']],
        'BS' => ['Bamboo Suite', ['701', '702', '703', '704', '705', '706', '707', '708', '709']],
        'GR' => ['Garden Room', ['801', '802', '803', '804', '805', '806', '807', '808', '809', '810', '811', '812']],
        'EXR' => ['Executive Room', ['901', '902']],
    ];

    public function run(): void
    {
        $roomSort = 0;

        foreach (array_keys(self::TYPES) as $typeIndex => $code) {
            [$name, $roomNumbers] = self::TYPES[$code];

            $type = RoomType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => ($typeIndex + 1) * 10],
            );

            foreach ($roomNumbers as $roomNo) {
                $roomSort += 10;

                $room = Room::firstOrNew(['room_no' => $roomNo]);
                $room->room_type_id = $type->id;
                $room->sort_order = $roomSort;
                if (! $room->exists) {
                    $room->active = true;
                }
                $room->save();
            }
        }
    }
}
