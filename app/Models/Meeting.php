<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasUuids;

    /** Délais de rappel proposés (heures avant le début) ; 0 désactive le rappel. */
    public const REMINDER_OPTIONS = [
        0   => 'Aucun rappel',
        1   => '1 heure avant',
        2   => '2 heures avant',
        4   => '4 heures avant',
        12  => '12 heures avant',
        24  => '1 jour avant',
        48  => '2 jours avant',
        72  => '3 jours avant',
        168 => '1 semaine avant',
    ];

    protected $fillable = [
        'reminder_hours_before',
        'title',
        'meeting_type',
        'meeting_room_id',
        'starts_at',
        'ends_at',
        'processing_deadline',
        'estimated_duration_minutes',
        'organizer_id',
        'minutes_writer_id',
        'validator_id',
        'issuing_administration_id',
        'sub_entity_code',
        'is_virtual',
        'meeting_link',
        'agenda',
        'minutes_template',
        'minutes_content',
            'template_variables',
            'template_sealed_path',
        'attachments',
        'priority',
        'confidentiality',
        'status',
        'workflow_status',
        'review_requested',
        'review_comment',
        'validation_requested_at',
        'validated_by',
        'validated_at',
        'writer_signature_path',
        'writer_signed_at',
        'published_at',
        'diffusion_email_subject',
        'diffusion_email_body',
        'diffusion_ack_required',
        'recurrence_series_id',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'recurrence_type',
        'recurrence_until',
        'recurrence_exceptions',
        'qr_token',
        'qr_valid_from',
        'qr_valid_until',
        'reminder_sent_at',
        'validation_reminder_sent_at',
        'deadline_alert_sent_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'processing_deadline' => 'datetime',
        'recurrence_until' => 'date',
        'attachments' => 'array',
        'recurrence_exceptions' => 'array',
            'template_variables' => 'array',
        'estimated_duration_minutes' => 'integer',
        'qr_valid_from' => 'datetime',
        'qr_valid_until' => 'datetime',
        'review_requested' => 'boolean',
        'validation_requested_at' => 'datetime',
        'validated_at' => 'datetime',
        'writer_signed_at' => 'datetime',
        'published_at' => 'datetime',
        'diffusion_ack_required' => 'boolean',
        'is_virtual' => 'boolean',
        'reminder_hours_before' => 'integer',
        'cancelled_at' => 'datetime',
        'archived_at' => 'datetime',
        'archive_attempted_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'validation_reminder_sent_at' => 'datetime',
        'deadline_alert_sent_at' => 'datetime',
    ];

    /** Un compte rendu validé (puis publié) n'est plus modifiable. */
    public function isMinutesLocked(): bool
    {
        return in_array($this->workflow_status, ['validated', 'published'], true);
    }

    /** Délai de rappel de cette réunion ; les réunions sans choix suivent le réglage du serveur. */
    public function reminderHours(?int $default = null): int
    {
        return $this->reminder_hours_before ?? $default ?? (int) config('meetings.reminder_hours_before_meeting', 24);
    }

    /** @return array<int, string> délais proposés, réglage du serveur compris */
    public static function reminderOptions(): array
    {
        $default = max(0, (int) config('meetings.reminder_hours_before_meeting', 24));
        $options = self::REMINDER_OPTIONS + [$default => $default . ' heures avant'];
        ksort($options);

        return $options;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Modifiable ou annulable tant qu'aucune présence n'est enregistrée et que le compte rendu est en brouillon. */
    public function isChangeable(): bool
    {
        return !$this->isCancelled()
            && ($this->workflow_status ?? 'draft') === 'draft'
            && !$this->attendances()->exists();
    }

    public function room()
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function minutesWriter()
    {
        return $this->belongsTo(User::class, 'minutes_writer_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function validatedByUser()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function administration()
    {
        return $this->belongsTo(IssuingAdministration::class, 'issuing_administration_id');
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function attendances()
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function minutesVersions()
    {
        return $this->hasMany(MeetingMinutesVersion::class)->orderByDesc('version_no');
    }
}
