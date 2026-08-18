<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce jointe rattachée à un dossier d'état civil.
 */
class CivilStatusRecordDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'record_id',
        'category',
        'label',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CivilStatusRecord::class, 'record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
