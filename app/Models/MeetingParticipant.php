<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'email',
        'full_name',
        'participant_role',
        'is_external',
        'organization',
        'invitation_status',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
