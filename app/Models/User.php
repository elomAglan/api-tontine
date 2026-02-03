<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Champs assignables en masse
     */
    protected $fillable = [
        'name',
        'phone',
        'country_code',
        'password',
    ];

    /**
     * Champs cachés pour la sérialisation JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Types de conversion automatique
     */
    protected $casts = [
        'password' => 'hashed', // Laravel 12+ hash automatique si assigné via User::create()
        // 'phone_verified_at' => 'datetime', // Si vérification téléphone plus tard
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Tontines auxquelles l'utilisateur participe
     */
public function tontines(): BelongsToMany
    {
        // On remplace 'tontine_members' par 'tontine_user'
        return $this->belongsToMany(Tontine::class, 'tontine_user')
                    ->withPivot('turn_order', 'role', 'status') // Crucial pour la tontine !
                    ->withTimestamps();
    }

    /**
     * Contributions effectuées par l'utilisateur
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Paiements effectués à l'utilisateur
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
