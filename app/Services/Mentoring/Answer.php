<?php

namespace App\Services\Mentoring;

use App\Models\Reflection;

/**
 * A resposta à reflexão e de onde ela veio: Reflection::SOURCE_AI, SOURCE_WRITTEN ou SOURCE_CARE.
 */
final class Answer
{
    public function __construct(
        public readonly string $text,
        public readonly string $source,
    ) {}

    public static function care(string $message): self
    {
        return new self($message, Reflection::SOURCE_CARE);
    }

    public static function written(string $text): self
    {
        return new self($text, Reflection::SOURCE_WRITTEN);
    }
}
