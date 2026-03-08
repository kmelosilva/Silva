<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mission_id',
        'completed',
        'assigned_date',
        'completed_at'
    ];

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getTodayMissions($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('assigned_date', today())
            ->with('mission')
            ->get();
    }

    public static function getAllMissionsByUser($userId)
    {
        return self::where('user_id', $userId)
            ->with('mission')
            ->get();
    }
}
