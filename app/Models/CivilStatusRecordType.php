<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type de dossier d'état civil paramétrable par une administration
 * (ex: Naissance, Mariage, Décès, ou tout autre type métier spécifique).
 */
class CivilStatusRecordType extends Model
{
    use HasUuids;

    protected $fillable = [
        'administration_type',
        'administration_id',
        'code',
        'name',
        'description',
        'required_documents',
        'fields_schema',
        'auto_template_id',
        'is_active',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'fields_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function autoTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'auto_template_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(CivilStatusRecord::class, 'record_type_id');
    }
}
