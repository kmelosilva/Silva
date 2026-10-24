<?php

namespace App\Services\Mentoring;

use App\Models\Reflection;

/**
 * A resposta à reflexão, de onde ela veio (Reflection::SOURCE_AI, SOURCE_WRITTEN ou
 * SOURCE_CARE) e se a IA foi chamada para dar a resposta, qualquer que tenha sido o
 * resultado: no máximo uma chamada por coordenada (RNF-01).
 */
final class Answer
{
    public function __construct(
        public readonly string $text,
        public readonly string $source,
        public readonly bool $calledAi = false,
    ) {}

    public static function care(string $message, bool $calledAi = false): self
    {
        return new self($message, Reflection::SOURCE_CARE, $calledAi);
    }

    public static function written(string $text, bool $calledAi = false): self
    {
        return new self($text, Reflection::SOURCE_WRITTEN, $calledAi);
    }

    public static function ai(string $text): self
    {
        return new self($text, Reflection::SOURCE_AI, true);
    }
}
