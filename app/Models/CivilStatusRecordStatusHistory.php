<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique des changements de statut d'un dossier d'état civil (traçabilité).
 */
class CivilStatusRecordStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'record_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CivilStatusRecord::class, 'record_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
