<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;



class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use   HasFactory, Notifiable;



    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
    ];


        protected $appends = ['photo_url'];

public function getPhotoUrlAttribute(): ?string
{
    if (!$this->photo) return null;
    $disk = config('media.disk', 'public');
    return Storage::disk($disk)->url($this->photo);
}


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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

}
