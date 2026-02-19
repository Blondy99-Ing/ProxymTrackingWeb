<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimGps extends Model
{
    protected $table = 'sim_gps';

    protected $fillable = [
        'objectid',
        'mac_id',
        'sim_number',
        'account_name',
    ];

    /* =========================
     * Helpers Unicode safe
     * ========================= */
    protected function toUpper(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        if ($v === null || $v === '') return null;
        return mb_strtoupper($v, 'UTF-8');
    }

    protected function toUcFirstLowerRest(?string $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        if ($v === null || $v === '') return null;

        $lower = mb_strtolower($v, 'UTF-8');
        $first = mb_substr($lower, 0, 1, 'UTF-8');
        $rest  = mb_substr($lower, 1, null, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8') . $rest;
    }

    /* =========================
     * Mutators (POST/PUT/PATCH)
     * ========================= */
    public function setMacIdAttribute($value): void
    {
        $this->attributes['mac_id'] = $this->toUpper($value);
    }

    public function setAccountNameAttribute($value): void
    {
        $this->attributes['account_name'] = $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Accessors (GET/JSON)
     * ========================= */
    public function getMacIdAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getAccountNameAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }
}