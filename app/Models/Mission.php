<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'xp',
        'category'
    ];

    public function userMissions()
    {
        return $this->hasMany(UserMission::class);
    }

    public function rules()
    {
        return $this->hasMany(MissionRule::class);
    }
}
