<?php

namespace App\Services\Mentoring;

/**
 * O que voltou da IA: o resultado, o texto (só em reply), os tokens e a duração.
 * Falha técnica também volta aqui, como resultado, e nunca como exceção.
 */
final class MentorReply
{
    /** A IA respondeu como submentor. */
    public const REPLY = 'reply';

    /** A IA reconheceu sinal de risco. */
    public const CARE = 'care';

    /** O modelo recusou o pedido (stop_reason refusal). */
    public const REFUSAL = 'refusal';

    /** Resposta cortada (max_tokens), sem texto ou fora do esquema. */
    public const INVALID = 'invalid';

    /** O tempo da chamada acabou. */
    public const TIMEOUT = 'timeout';

    /** Erro de rede ou da API. */
    public const ERROR = 'error';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $text,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $durationMs,
    ) {}
}
