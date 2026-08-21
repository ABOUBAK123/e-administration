<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentShare extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id', 'shared_by', 'mode', 'recipient_name', 'recipient_email',
        'recipient_administration_id', 'applicant_full_name', 'applicant_matricule',
        'applicant_email', 'applicant_phone', 'applicant_rib', 'nni_hash', 'nni_masked', 'tracking_number', 'permission', 'has_delay', 'delay_value', 'delay_unit',
        'expires_at', 'reception_status',
    ];

    protected $casts = ['expires_at' => 'datetime', 'has_delay' => 'boolean'];

    public function document() { return $this->belongsTo(Document::class); }
    public function sharedBy() { return $this->belongsTo(User::class, 'shared_by'); }
    public function recipientAdministration() { return $this->belongsTo(RecipientAdministration::class, 'recipient_administration_id'); }
}
