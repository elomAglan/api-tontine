<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = ['tontine_id', 'user_id', 'round_number', 'amount', 'status'];
}
