<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ville extends Model
{
    protected $fillable = [
        'code_ville',
        'name',
        'geom'
    ];

    protected $casts = [
        'geom' => 'array',
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
    public function setCodeVilleAttribute($value): void
    {
        $this->attributes['code_ville'] = $this->toUpper($value);
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Accessors (GET/JSON)
     * ========================= */
    public function getCodeVilleAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getNameAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }
}