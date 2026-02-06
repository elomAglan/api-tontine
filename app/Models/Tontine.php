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
        'late_fee',
        'start_date',
        'creator_id',
        'status',
        'order_type',
        'order_locked',
        'current_turn', 
    ];

    protected $appends = [
        'members_count', 
        'round_deadline', 
        'days_left', 
        'is_overdue'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'amount' => 'double',
        'current_turn' => 'integer',
        'frequency_days' => 'integer',
        'creator_id' => 'integer',
        'order_locked' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | ATTRIBUTS CALCULÉS (APPENDS)
    |--------------------------------------------------------------------------
    */

    public function getMembersCountAttribute(): int
    {
        return $this->users()->count();
    }

    public function getRoundDeadlineAttribute()
    {
        if (!$this->start_date || $this->status !== 'active') {
            return null;
        }
        // Deadline = Date de début + (Nombre de jours * tour actuel)
        return $this->start_date->copy()->addDays($this->frequency_days * ($this->current_turn ?? 1));
    }

    public function getDaysLeftAttribute(): int
    {
        $deadline = $this->round_deadline;
        if (!$deadline) return 0;
        return (int) now()->diffInDays($deadline, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        $deadline = $this->round_deadline;
        if (!$deadline) return false;
        return now()->greaterThan($deadline);
    }

    /*
    |--------------------------------------------------------------------------
    | SYSTÈME D'HISTORIQUE (LOG D'ACTIVITÉ)
    |--------------------------------------------------------------------------
    */

    /**
     * Enregistre une trace dans l'historique de la tontine
     */
    public function logActivity(string $type, string $description, int $userId = null, float $amount = 0)
    {
        return $this->histories()->create([
            'user_id' => $userId,
            'type' => $type, // 'start', 'payment', 'payout', 'penalty', 'round_closed'
            'amount' => $amount,
            'round_number' => $this->current_turn,
            'description' => $description,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function histories(): HasMany
    {
        return $this->hasMany(TontineHistory::class)->latest();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tontine_user')
                    ->withPivot('turn_order', 'role', 'status')
                    ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function penalties(): HasMany 
    {
        return $this->hasMany(Penalty::class);
    }
}