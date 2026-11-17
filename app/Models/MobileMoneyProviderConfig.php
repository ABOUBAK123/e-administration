<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyProviderConfig extends Model
{
    use HasUuids;

    protected $table = 'mobile_money_provider_configs';

    protected $fillable = [
        'administration_id',
        'administration_type',
        'provider',
        'label',
        'is_active',
        'endpoint',
        'environment',
        'currency',
        'api_key',
        'api_secret',
        'merchant_id',
        'callback_url',
        'verify_ssl',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'verify_ssl' => 'boolean',
    ];

    public const PROVIDERS = [
        'orange_money' => 'Orange Money',
        'mtn_money'    => 'MTN Mobile Money',
        'moov_money'   => 'Moov Money',
        'wave'         => 'Wave',
        'autre'        => 'Autre',
    ];

    public function issuingAdministration(): BelongsTo
    {
        return $this->belongsTo(IssuingAdministration::class, 'administration_id');
    }

    public function getProviderLabelAttribute(): string
    {
        if ($this->provider === 'autre' && $this->label) {
            return $this->label;
        }

        return self::PROVIDERS[$this->provider] ?? ($this->label ?: $this->provider);
    }
}
