<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'xp',
        'energy',
        'last_energy_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

