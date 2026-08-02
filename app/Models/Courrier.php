<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courrier extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'numero',
        'type',
        'objet',
        'expediteur',
        'destinataire',
        'numero_emission',
        'urgence',
        'date_emission',
        'observations',
        'statut',
        'enregistre_par',
        'administration_id',
        'emetteur_administration_id',
        'destinataire_administration_id',
        'source_document_id',
        'is_system_generated',
        'sub_entity_code',
        'impute_a',
        'impute_par',
        'impute_le',
        'instruction_nom',
        'instruction_desc',
        'delai_traitement',
        'pieces_jointes',
        'accuse_reception',
        'fichier_reponse',
        'reponse_nom',
        'reponse_statut',
        'workflow_participants',
        'traite_par',
        'traite_le',
    ];

    protected $casts = [
        'pieces_jointes' => 'array',
        'date_emission'  => 'date',
        'impute_le'      => 'datetime',
        'delai_traitement' => 'date',
        'workflow_participants' => 'array',
        'traite_le' => 'datetime',
        'is_system_generated' => 'boolean',
    ];

    /** Libellé de priorité */
    public function getPrioriteLibelleAttribute(): string
    {
        return match($this->urgence) {
            'urgent'      => 'Urgent',
            'tres_urgent' => 'Très urgent',
            default       => 'Normale',
        };
    }

    /** Libellé de statut */
    public function getStatutLibelleAttribute(): string
    {
        return match($this->statut) {
            'en_traitement' => 'En traitement',
            'traite'        => 'Traité',
            default         => 'En attente',
        };
    }

    // ─── Relations ────────────────────────────────────────────────

    public function enregistrePar()
    {
        return $this->belongsTo(User::class, 'enregistre_par');
    }

    public function administration()
    {
        return $this->belongsTo(IssuingAdministration::class, 'administration_id');
    }

    public function emetteurAdministration()
    {
        return $this->belongsTo(IssuingAdministration::class, 'emetteur_administration_id');
    }

    public function destinataireAdministration()
    {
        return $this->belongsTo(RecipientAdministration::class, 'destinataire_administration_id');
    }

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function imputePar()
    {
        return $this->belongsTo(User::class, 'impute_par');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    /** Courriers arrivés en attente d'imputation pour une administration */
    public function scopePourImputation($query, string $administrationId)
    {
        return $query->where('type', 'arrive')
                     ->where('statut', 'en_attente')
                     ->where('administration_id', $administrationId);
    }
}
