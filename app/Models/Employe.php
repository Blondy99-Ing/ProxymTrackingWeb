<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Employe extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'unique_id',
        'nom',
        'prenom',
        'phone',
        'email',
        'ville',
        'quartier',
        'photo',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* =========================
     * Helpers (Unicode safe)
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
    public function setUniqueIdAttribute($value): void
    {
        $this->attributes['unique_id'] = $this->toUpper($value);
    }

    public function setNomAttribute($value): void
    {
        $this->attributes['nom'] = $this->toUpper($value);
    }

    public function setPrenomAttribute($value): void
    {
        $this->attributes['prenom'] = $this->toUcFirstLowerRest($value);
    }

    public function setVilleAttribute($value): void
    {
        $this->attributes['ville'] = $this->toUcFirstLowerRest($value);
    }

    public function setQuartierAttribute($value): void
    {
        $this->attributes['quartier'] = $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Accessors (GET/JSON)
     * ========================= */
    public function getUniqueIdAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getNomAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getPrenomAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    public function getVilleAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    public function getQuartierAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Photo url
     * ========================= */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;
        return Storage::disk('public')->url($this->photo);
    }

    /* =========================
     * Relations & Roles
     * ========================= */
    public function commands()
    {
        return $this->hasMany(Commande::class);
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isCallCenter(): bool
    {
        return $this->hasRole('call_center');
    }
}