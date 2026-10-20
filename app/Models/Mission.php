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
}
