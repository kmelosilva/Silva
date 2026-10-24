<?php

namespace App\Exceptions;

/**
 * Uma regra da mentoria recusou o pedido.
 */
class MentoringRefused extends RequestRefused
{
    public static function requiresCompletion(): self
    {
        return new self('reflection_requires_completion', 'Cumpra a coordenada antes de escrever sobre ela.', 409);
    }
}
