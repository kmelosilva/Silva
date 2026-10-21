<?php

namespace App\Exceptions;

/**
 * Uma regra do alistamento recusou o pedido: passo fora de ordem ou alistamento já concluído.
 */
class EnlistmentRefused extends RequestRefused
{
    public static function oathRequired(): self
    {
        return new self('oath_required', 'Aceite o juramento para seguir com o alistamento.', 409);
    }

    public static function codenameRequired(): self
    {
        return new self('codename_required', 'Escolha seu codinome para concluir o alistamento.', 409);
    }

    public static function alreadyEnlisted(): self
    {
        return new self('already_enlisted', 'Seu alistamento já foi concluído.', 409);
    }
}
