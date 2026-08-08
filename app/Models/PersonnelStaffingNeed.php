<?php

namespace App\Models;

use App\Models\Concerns\LogsPersonnelActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class PersonnelStaffingNeed extends Model
{
    use HasUuids;
    use LogsActivity;
    use LogsPersonnelActivity;

    protected $fillable = [
        'administration_type',
        'administration_id',
        'sub_entity_id',
        'job_title',
        'required_count',
        'current_count',
        'priority',
        'status',
        'target_date',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'required_count' => 'integer',
        'current_count' => 'integer',
        'target_date' => 'date',
    ];

    public function subEntity(): BelongsTo
    {
        return $this->belongsTo(SubEntity::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getGapAttribute(): int
    {
        return max(0, (int) $this->required_count - (int) $this->current_count);
    }
}
