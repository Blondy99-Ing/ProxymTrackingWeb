<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voiture extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'voiture_unique_id',
        'immatriculation',
        'vin',
        'mac_id_gps',
        'sim_gps',
        'marque',
        'model',
        'couleur',
        'photo',
        'region_id',
        'region_name',
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius',
        'geofence_zone',
    ];

    protected $appends = ['photo_url'];

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
    public function setVoitureUniqueIdAttribute($value): void
    {
        $this->attributes['voiture_unique_id'] = $this->toUpper($value);
    }

    public function setImmatriculationAttribute($value): void
    {
        $this->attributes['immatriculation'] = $this->toUpper($value);
    }

    public function setVinAttribute($value): void
    {
        $this->attributes['vin'] = $this->toUpper($value);
    }

    public function setMacIdGpsAttribute($value): void
    {
        $this->attributes['mac_id_gps'] = $this->toUpper($value);
    }

    // ✅ marque en MAJUSCULE
    public function setMarqueAttribute($value): void
    {
        $this->attributes['marque'] = $this->toUpper($value);
    }

    // model/couleur/region_name => 1ère lettre maj
    public function setModelAttribute($value): void
    {
        $this->attributes['model'] = $this->toUcFirstLowerRest($value);
    }

    public function setCouleurAttribute($value): void
    {
        $this->attributes['couleur'] = $this->toUcFirstLowerRest($value);
    }

    public function setRegionNameAttribute($value): void
    {
        $this->attributes['region_name'] = $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Accessors (GET/JSON output)
     * ========================= */
    public function getVoitureUniqueIdAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getImmatriculationAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getVinAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getMacIdGpsAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    // ✅ marque en MAJUSCULE
    public function getMarqueAttribute($value): ?string
    {
        return $this->toUpper($value);
    }

    public function getModelAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    public function getCouleurAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    public function getRegionNameAttribute($value): ?string
    {
        return $this->toUcFirstLowerRest($value);
    }

    /* =========================
     * Relations & Other
     * ========================= */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(Location::class, 'mac_id_gps', 'mac_id_gps')
            ->latestOfMany('datetime');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;

        $disk = config('media.disk', 'public');
        return Storage::disk($disk)->url($this->photo);
    }

    public function utilisateur()
    {
        return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsToMany(\App\Models\User::class, 'association_user_voitures', 'voiture_id', 'user_id');
    }

    public function alerts()
    {
        return $this->hasMany(\App\Models\Alert::class, 'voiture_id');
    }

    public function trajets()
    {
        return $this->hasMany(\App\Models\Trajet::class, 'vehicle_id');
    }

    public function commands()
    {
        return $this->hasMany(Commande::class, 'vehicule_id');
    }
}