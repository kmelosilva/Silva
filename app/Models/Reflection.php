<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que o agente escreveu sobre uma coordenada cumprida, com a resposta que recebeu.
 * Só o próprio agente vê, e pode apagar.
 */
class Reflection extends Model
{
    /** A resposta foi gerada pela IA. */
    public const SOURCE_AI = 'ai';

    /** A resposta é um texto escrito do submentor. */
    public const SOURCE_WRITTEN = 'written';

    /** A resposta é a mensagem de cuidado, fora do personagem. */
    public const SOURCE_CARE = 'care';

    protected $fillable = [
        'user_id',
        'coordinate_completion_id',
        'text',
        'reply',
        'reply_source',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(CoordinateCompletion::class, 'coordinate_completion_id');
    }
}
