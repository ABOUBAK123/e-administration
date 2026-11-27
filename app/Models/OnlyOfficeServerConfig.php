<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Serveur OnlyOffice propre à une administration. */
class OnlyOfficeServerConfig extends Model
{
    use HasUuids;

    protected $table = 'onlyoffice_server_configs';

    protected $fillable = [
        'administration_id',
        'administration_type',
        'is_active',
        'server_url',
        'secret',
        'disable_cert',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'disable_cert' => 'boolean',
        'secret'       => 'encrypted',
    ];

    protected $hidden = ['secret'];
}
