<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Tontine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'frequency_days',
        'late_fee', // Assure-toi qu'il est bien là pour les amendes
        'start_date',
        'creator_id',
        'status',
        'order_type',
        'order_locked',
    ];

    /**
     * AJOUT : Force l'inclusion de ces calculs dans les réponses JSON
     */
    protected $appends = ['current_turn', 'is_finished'];

    /**
     * CALCUL AUTOMATIQUE DU TOUR ACTUEL
     */
    public function getCurrentTurnAttribute()
    {
        if (!$this->start_date || $this->status === 'pending') {
            return null;
        }

        $startDate = Carbon::parse($this->start_date);
        $daysPassed = $startDate->diffInDays(now());

        $currentTurn = floor($daysPassed / $this->frequency_days) + 1;
        $totalMembers = $this->users()->count();

        return ($currentTurn > $totalMembers) ? $totalMembers : (int) $currentTurn;
    }

    /**
     * VÉRIFIER SI LA TONTINE EST TERMINÉE
     */
    public function getIsFinishedAttribute(): bool
    {
        if (!$this->start_date || $this->status === 'pending') return false;

        $totalMembers = $this->users()->count();
        $totalDaysDuration = $totalMembers * $this->frequency_days;
        $startDate = Carbon::parse($this->start_date);

        return now()->diffInDays($startDate) >= $totalDaysDuration;
    }

    /**
     * RELATIONS
     */
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tontine_user')
                    ->withPivot('turn_order', 'role', 'status')
                    ->withTimestamps();
    }

    public function penalties(): HasMany 
    {
        return $this->hasMany(Penalty::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}