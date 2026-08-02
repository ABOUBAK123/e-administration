<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RequestedAct extends Model
{
    use HasUuids;

    protected $fillable = [
        'administration_id', 'direction_code', 'recipient_administration_id',
        'motif', 'document_name', 'required_documents', 'applicant_fields',
        'auto_generate_enabled', 'auto_template_id', 'unique_key_field',
        'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'required_documents' => 'array',
        'applicant_fields'   => 'array',
        'auto_generate_enabled' => 'boolean',
    ];

    public function administration()
    {
        return $this->belongsTo(IssuingAdministration::class, 'administration_id');
    }

    public function recipientAdministration()
    {
        return $this->belongsTo(RecipientAdministration::class, 'recipient_administration_id');
    }

    public function autoTemplate()
    {
        return $this->belongsTo(DocumentTemplate::class, 'auto_template_id');
    }
}
