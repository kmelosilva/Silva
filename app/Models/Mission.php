<?php

namespace App\Models;

use App\Enums\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'briefing',
        'category',
        'is_entry',
        'position',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => Category::class,
            'is_entry' => 'boolean',
            'position' => 'integer',
            'retired_at' => 'datetime',
        ];
    }

    public function userMissions()
    {
        return $this->hasMany(UserMission::class);
    }

    public function rules()
    {
        return $this->hasMany(MissionRule::class);
    }

    public function coordinates(): HasMany
    {
        return $this->hasMany(Coordinate::class);
    }

    public function publishedCoordinates(): HasMany
    {
        return $this->coordinates()->published()->orderBy('position');
    }

    /**
     * Missões que aparecem nos módulos: não aposentadas, fora da entrada,
     * com categoria e com pelo menos uma coordenada publicada.
     */
    public function scopeInModules(Builder $query): void
    {
        $query->whereNull('retired_at')
            ->where('is_entry', false)
            ->whereNotNull('category')
            ->whereHas('coordinates', fn (Builder $coordinates) => $coordinates->published());
    }

    /**
     * A missão de entrada ("Seja um agente"): não aposentada e com coordenada publicada.
     * Fica fora dos módulos; o agente a recebe ao concluir o alistamento.
     */
    public function scopeEntry(Builder $query): void
    {
        $query->whereNull('retired_at')
            ->where('is_entry', true)
            ->whereHas('coordinates', fn (Builder $coordinates) => $coordinates->published());
    }

    /**
     * Missões que o agente pode cumprir: as dos módulos e a de entrada.
     */
    public function scopePlayable(Builder $query): void
    {
        $query->where(fn (Builder $missions) => $missions
            ->where(fn (Builder $modules) => $modules->inModules())
            ->orWhere(fn (Builder $entry) => $entry->entry()));
    }

    /**
     * Missões que a sugestão do dia pode oferecer a um agente desse nível, nesse dia: sem regra,
     * ou com pelo menos uma regra diária, aleatória ou de desbloqueio por nível que o nível e a
     * data permitem. Semanais e de evento ficam para a spec do tema semanal; `chance` não é usado.
     */
    public function scopeSuggestableFor(Builder $query, int $level, string $day): void
    {
        $query->where(fn (Builder $missions) => $missions
            ->whereDoesntHave('rules')
            ->orWhereHas('rules', fn (Builder $rules) => $rules
                ->whereIn('type', ['daily', 'random', 'level_unlock'])
                ->where('min_level', '<=', $level)
                ->where(fn (Builder $dates) => $dates->whereNull('start_date')->orWhere('start_date', '<=', $day))
                ->where(fn (Builder $dates) => $dates->whereNull('end_date')->orWhere('end_date', '>=', $day))));
    }
}
