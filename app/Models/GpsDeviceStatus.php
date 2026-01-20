<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsDeviceStatus extends Model
{
    protected $table = 'gps_device_status';

    protected $fillable = [
        'mac_id_gps','account_name','state','is_online',
        'last_seen_at','last_server_at',
        'offline_started_at','offline_seconds','threshold_minutes',
        'last_change_at',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_server_at' => 'datetime',
        'offline_started_at' => 'datetime',
        'last_change_at' => 'datetime',
    ];
}
