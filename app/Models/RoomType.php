<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A room type as the front desk system names it, e.g. PH — Pension House. */
class RoomType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sort_order',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
