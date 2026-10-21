<?php

namespace App\Exceptions;

use App\Models\Coordinate;

/**
 * Uma regra do ciclo de progresso recusou o pedido (aceitar ou cumprir).
 */
class ProgressRefused extends RequestRefused
{
    public static function alreadyAccepted(): self
    {
        return new self('mission_already_accepted', 'Esta missão já está em andamento.', 409);
    }

    public static function alreadyCompleted(): self
    {
        return new self('mission_already_completed', 'Você já concluiu esta missão.', 409);
    }

    public static function notAccepted(): self
    {
        return new self('mission_not_accepted', 'Aceite a missão para começar a cumprir as coordenadas.', 409);
    }

    /**
     * @param  int  $number  a posição da próxima coordenada entre as publicadas, a partir de 1
     */
    public static function outOfOrder(Coordinate $next, int $number): self
    {
        return new self(
            'coordinate_out_of_order',
            "Cumpra antes a coordenada {$number}: {$next->title}.",
            422,
            ['next_coordinate' => $next->slug],
        );
    }
}
