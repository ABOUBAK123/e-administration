<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Accusé de réception du compte rendu, un par destinataire et par réunion. */
class MeetingMinutesReceipt extends Model
{
    use HasUuids;

    protected $fillable = ['meeting_id', 'email', 'name', 'token', 'sent_at', 'send_count', 'acknowledged_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    /** Le lien d'un destinataire reste le même d'un envoi à l'autre. */
    public static function prepare(Meeting $meeting, string $email, ?string $name): self
    {
        return self::firstOrCreate(
            ['meeting_id' => $meeting->id, 'email' => mb_strtolower($email)],
            ['name' => $name, 'token' => Str::random(48)]
        );
    }

    public function link(): string
    {
        return route('meetings.receipt.show', ['token' => $this->token]);
    }

    public function markSent(): void
    {
        $this->forceFill(['sent_at' => now(), 'send_count' => $this->send_count + 1])->save();
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
