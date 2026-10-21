<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma missão aceita pelo agente: em andamento sem completed_at, concluída com ele.
 */
class UserMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mission_id',
        'accepted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

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
