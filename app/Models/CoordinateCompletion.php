<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Uma coordenada cumprida por um agente, com o XP creditado naquele momento.
 * ai_replied_at marca que a IA já respondeu a uma reflexão sobre ela, e fica mesmo
 * depois de a reflexão ser apagada (uma resposta por IA por coordenada).
 */
class CoordinateCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'coordinate_id',
        'xp',
        'completed_at',
        'ai_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'completed_at' => 'datetime',
            'ai_replied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coordinate(): BelongsTo
    {
        return $this->belongsTo(Coordinate::class);
    }

    public function reflection(): HasOne
    {
        return $this->hasOne(Reflection::class);
    }
}
