<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an incident's timeline ("Monitoring - Naayos na …"). */
class StatusIncidentUpdate extends Model
{
    protected $fillable = ['status_incident_id', 'status', 'message', 'created_by'];

    protected function casts(): array
    {
        return ['status' => IncidentStatus::class];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusIncident::class, 'status_incident_id');
    }
}
