<?php

namespace App\Support;

/**
 * O codinome do agente: um nome seguido de "da Silva", porque todo mundo vira Silva.
 * O agente escolhe só o nome; o sufixo é fixo.
 */
final class Codename
{
    public const SUFFIX = 'da Silva';

    public const MAX_NAME_LENGTH = 30;

    /**
     * Letras (com acento), com espaço, hífen ou apóstrofo entre elas.
     */
    public const NAME_PATTERN = "/^[\pL\pM]+(?:[ '’-][\pL\pM]+)*$/u";

    /**
     * O nome sugerido: a primeira palavra do nome da conta que tem letras, só com as letras,
     * com a inicial maiúscula. Um nome sem letras não sugere nada.
     */
    public static function suggestFor(string $accountName): ?string
    {
        foreach (preg_split('/\s+/u', trim($accountName)) as $word) {
            $letters = trim(preg_replace("/[^\pL\pM'’-]/u", '', $word), "'’-");

            if ($letters !== '') {
                $letters = mb_substr($letters, 0, self::MAX_NAME_LENGTH);

                return mb_strtoupper(mb_substr($letters, 0, 1)).mb_substr($letters, 1);
            }
        }

        return null;
    }

    /**
     * O nome como o agente digitou, limpo: espaços repetidos viram um só, e um "da Silva"
     * digitado no fim sai, para o codinome não ficar "Kayque da Silva da Silva".
     */
    public static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return trim(preg_replace('/\s*\bda\s+silva$/iu', '', $name));
    }

    /**
     * O codinome completo, a partir do nome já validado.
     */
    public static function build(string $name): string
    {
        return self::normalizeName($name).' '.self::SUFFIX;
    }
}
