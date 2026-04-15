<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_unique_id',
        'nom',
        'prenom',
        'phone',
        'email',
        'ville',
        'quartier',
        'photo',
        'password',
        'role_id',
        'partner_id',
        'created_by',
    ];

    protected $appends = ['photo_url'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

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
    public function setUserUniqueIdAttribute($value): void
    {
        $this->attributes['user_unique_id'] = $this->toUpper($value);
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
     * Accessors (GET/JSON output)
     * => corrige même si la DB est "sale"
     * ========================= */
    public function getUserUniqueIdAttribute($value): ?string
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
     * Appends
     * ========================= */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) return null;
        $disk = config('media.disk', 'public');
        return Storage::disk($disk)->url($this->photo);
    }

    /* =========================
     * Relations
     * ========================= */
    public function voitures()
    {
        return $this->belongsToMany(Voiture::class, 'association_user_voitures', 'user_id', 'voiture_id');
    }

    public function commands()
    {
        return $this->hasMany(Commande::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function affectationVoitureActuellePartner(): HasMany
    {
        return $this->hasMany(AssociationChauffeurVoiturePartner::class, 'chauffeur_id');
    }

    public function historiqueAffectationsVoituresPartner(): HasMany
    {
        return $this->hasMany(HistoriqueAssociationChauffeurVoiturePartner::class, 'chauffeur_id')
            ->orderByDesc('start_at');
    }




    public function subscriptions()
{
    return $this->hasMany(\App\Models\Subscription::class, 'user_id');
}

public function payments()
{
    return $this->hasMany(\App\Models\Payment::class, 'user_id');
}

public function recordedPayments()
{
    return $this->hasMany(\App\Models\Payment::class, 'recorded_by');
}
}