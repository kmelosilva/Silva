<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordinate extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'slug',
        'position',
        'title',
        'content',
        'xp',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'xp' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Coordenada publicada: não aposentada e com texto.
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNull('retired_at')
            ->whereNotNull('content')
            ->where('content', '!=', '');
    }
}
