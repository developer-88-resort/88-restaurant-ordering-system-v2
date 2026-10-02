<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest room a Room Charge can go on. Labelled the way the front desk
 * system shows it: "<room_no> <type code>", e.g. "511 PH".
 */
class Room extends Model
{
    protected $fillable = [
        'room_no',
        'room_type_id',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Front desk order: by type (VR → … → EXR), then the room's own order. */
    public function scopeInFrontDeskOrder(Builder $query): Builder
    {
        return $query
            ->join('room_types', 'room_types.id', '=', 'rooms.room_type_id')
            ->orderBy('room_types.sort_order')
            ->orderBy('rooms.sort_order')
            ->orderBy('rooms.room_no')
            ->select('rooms.*');
    }

    /** "511 PH" */
    public function label(): string
    {
        return self::formatLabel($this->room_no, $this->roomType?->code);
    }

    public static function formatLabel(?string $roomNo, ?string $typeCode): string
    {
        return trim(($roomNo ?? '').' '.($typeCode ?? ''));
    }
}
