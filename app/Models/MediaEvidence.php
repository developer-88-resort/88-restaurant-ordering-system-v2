<?php

namespace App\Models;

use App\Enums\MediaEvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Supporting-documentation file (weighing photo/video, terminal-slip
 * photo) linked to exactly one record. Stored on the private local disk;
 * only served through an authenticated, role-checked download route. This
 * is documentation, not legal certification of anything.
 */
class MediaEvidence extends Model
{
    protected $table = 'media_evidence';

    protected $fillable = [
        'evidenceable_type',
        'evidenceable_id',
        'media_type',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'note',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'media_type' => MediaEvidenceType::class,
            'size_bytes' => 'integer',
        ];
    }

    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
