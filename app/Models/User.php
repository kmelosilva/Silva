<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;

    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Toda conta nasce com o seu agente (nível 1, 0 XP).
     */
    protected static function booted(): void
    {
        static::created(fn (User $user) => $user->agent()->create());
    }

    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * O agente da conta, lido do banco a cada chamada e criado agora se faltar
     * (conta que escapou da criação e da migração).
     */
    public function ensureAgent(): Agent
    {
        $agent = $this->agent()->firstOrCreate();
        $this->setRelation('agent', $agent);

        return $agent;
    }

    public function missions()
    {
        return $this->hasMany(UserMission::class);
    }

    public function coordinateCompletions(): HasMany
    {
        return $this->hasMany(CoordinateCompletion::class);
    }
}
