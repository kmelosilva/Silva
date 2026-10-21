<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordinate extends Model
{
    use HasFactory;

    /**
     * O aviso de cuidado das coordenadas com esforço físico: curto, sem alarme, sem esconder o texto.
     */
    public const CARE_NOTICE = 'Vá no seu ritmo e respeite os limites do seu corpo. Se você tem alguma condição de saúde, ou se algo doer, procure a orientação de um profissional.';

    protected $fillable = [
        'mission_id',
        'slug',
        'position',
        'title',
        'content',
        'physical_effort',
        'xp',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'xp' => 'integer',
            'physical_effort' => 'boolean',
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
