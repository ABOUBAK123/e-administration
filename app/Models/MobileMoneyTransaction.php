<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMoneyTransaction extends Model
{
    use HasUuids;

    protected $table = 'mobile_money_transactions';

    protected $fillable = [
        'act_request_submission_id',
        'mobile_money_provider_config_id',
        'provider',
        'external_id',
        'phone_number',
        'amount',
        'currency',
        'status',
        'financial_transaction_id',
        'reason',
        'raw_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ActRequestSubmission::class, 'act_request_submission_id');
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyProviderConfig::class, 'mobile_money_provider_config_id');
    }
}
