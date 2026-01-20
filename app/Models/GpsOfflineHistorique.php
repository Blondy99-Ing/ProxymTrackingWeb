<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsOfflineHistorique extends Model
{
    protected $table = 'gps_offline_historique';

    protected $fillable = [
        'mac_id_gps','account_name',
        'started_at','detected_at','ended_at','duration_seconds',
        'last_seen_at','last_server_at','threshold_minutes','meta'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'detected_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_server_at' => 'datetime',
        'meta' => 'array',
    ];
}
