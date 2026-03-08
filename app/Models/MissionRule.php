<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'type',
        'min_level',
        'chance',
        'start_date',
        'end_date'
    ];

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }
}
