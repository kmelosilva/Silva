<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma coordenada cumprida por um agente, com o XP creditado naquele momento.
 */
class CoordinateCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'coordinate_id',
        'xp',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'completed_at' => 'datetime',
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
}
