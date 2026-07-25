<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Area extends Model
{
    use LogsAuditActivity;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Area $area) {
            $area->slug ??= Str::slug($area->name);
        });
    }

    public function categories(): HasMany
    {
        return $this->hasMany(SpaceCategory::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }
}
