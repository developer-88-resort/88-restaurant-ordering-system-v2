<?php

namespace App\Models;

use App\Enums\IncidentImpact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One incident on the status page (/status and the maintenance page):
 * opened when Maintenance Mode is switched on, resolved when it is
 * switched off.
 */
class StatusIncident extends Model
{
    /** The parts of the system an incident can affect, as the uptime rows. */
    public const COMPONENTS = [
        'ordering' => 'Ordering System',
        'customer' => 'Customer QR Ordering',
        'printing' => 'Kitchen Printing',
        'massage' => 'Massage',
    ];

    protected $fillable = ['title', 'impact', 'components', 'started_at', 'resolved_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'impact' => IncidentImpact::class,
            'components' => 'array',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusIncidentUpdate::class)->latest('created_at')->latest('id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public static function componentLabels(): array
    {
        return array_map(fn (string $label) => __($label), self::COMPONENTS);
    }
}
