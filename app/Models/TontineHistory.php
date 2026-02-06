<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TontineHistory extends Model
{
    protected $fillable = ['tontine_id', 'user_id', 'type', 'amount', 'round_number', 'description'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
