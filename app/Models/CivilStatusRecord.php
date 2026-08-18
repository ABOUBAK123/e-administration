<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier d'état civil (base documentaire interne d'une administration à numériser),
 * distinct des demandes publiques d'actes (RequestedAct / ActRequestSubmission).
 */
class CivilStatusRecord extends Model
{
    use HasUuids;

    /**
     * Statuts possibles d'un dossier, dans l'ordre logique du traitement.
     */
    public const STATUSES = [
        'draft'             => 'Brouillon',
        'pending_documents' => 'Pièces en attente',
        'under_review'      => 'En vérification',
        'validated'         => 'Validé',
        'generated'         => 'Acte généré',
        'rejected'          => 'Rejeté',
        'archived'          => 'Archivé',
    ];

    protected $fillable = [
        'record_type_id',
        'administration_type',
        'administration_id',
        'sub_entity_id',
        'reference_number',
        'subject_name',
        'event_date',
        'event_place',
        'declarant_name',
        'declarant_contact',
        'data',
        'status',
        'assigned_to',
        'created_by',
        'validated_by',
        'validated_at',
        'generated_document_id',
        'notes',
    ];

    protected $casts = [
        'event_date'   => 'date',
        'validated_at' => 'datetime',
        'data'         => 'array',
    ];

    public function recordType(): BelongsTo
    {
        return $this->belongsTo(CivilStatusRecordType::class, 'record_type_id');
    }

    public function subEntity(): BelongsTo
    {
        return $this->belongsTo(SubEntity::class, 'sub_entity_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'generated_document_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CivilStatusRecordDocument::class, 'record_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CivilStatusRecordStatusHistory::class, 'record_id')->latest('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
