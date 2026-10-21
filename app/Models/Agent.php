<?php

namespace App\Models;

use App\Support\AgentDay;
use Illuminate\Database\Eloquent\Model;

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
}
