<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RoutingRule extends Model
{
    use HasUuids;

    protected $fillable = ['template_id', 'target_type', 'recipient_id', 'target_user_id', 'condition_field', 'condition_operator', 'condition_value', 'priority', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'priority' => 'integer'];

    public function template() { return $this->belongsTo(DocumentTemplate::class, 'template_id'); }
    public function recipient() { return $this->belongsTo(RecipientAdministration::class, 'recipient_id'); }
    public function targetUser() { return $this->belongsTo(User::class, 'target_user_id'); }
}
