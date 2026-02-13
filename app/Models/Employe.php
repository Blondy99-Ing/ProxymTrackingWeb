<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;


class Employe extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use   HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];




//gestion des image des employes
protected $appends = ['photo_url'];

public function getPhotoUrlAttribute(): ?string
{
    if (!$this->photo) return null;
    return Storage::disk('public')->url($this->photo); // => /storage/....
}


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



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
