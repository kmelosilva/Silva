<?php

namespace App\Models;

use App\Enums\Category;
use App\Support\AgentDay;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Agent extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'xp',
        'energy',
        'last_energy_at',
        'streak_current',
        'streak_best',
        'last_active_on',
        'codename',
        'priorities',
        'submentors_met',
        'oath_accepted_at',
        'enlisted_at',
    ];

    /**
     * Os mesmos defaults da tabela, para o agente recém-criado já vir completo.
     */
    protected $attributes = [
        'level' => 1,
        'xp' => 0,
        'energy' => 100,
        'streak_current' => 0,
        'streak_best' => 0,
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'xp' => 'integer',
            'streak_current' => 'integer',
            'streak_best' => 'integer',
            'priorities' => AsEnumCollection::of(Category::class),
            'submentors_met' => AsEnumCollection::of(Category::class),
            'oath_accepted_at' => 'datetime',
            'enlisted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A sequência como o agente a vê: se o último dia ativo não foi hoje nem ontem,
     * ela já foi interrompida e aparece como 0. A coluna só muda na próxima atividade.
     */
    public function currentStreak(): int
    {
        return in_array($this->last_active_on, [AgentDay::today(), AgentDay::yesterday()], true)
            ? $this->streak_current
            : 0;
    }

    /**
     * As áreas de prioridade do agente, sempre como coleção: sem prioridade é uma coleção vazia.
     *
     * @return Collection<int, Category>
     */
    public function priorityAreas(): Collection
    {
        return $this->priorities ?? new Collection;
    }

    /**
     * As áreas cujo submentor o agente já conheceu, sempre como coleção.
     *
     * @return Collection<int, Category>
     */
    public function metSubmentors(): Collection
    {
        return $this->submentors_met ?? new Collection;
    }
}
