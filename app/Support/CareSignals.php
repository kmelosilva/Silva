<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sinais de risco à vida ou de sofrimento intenso numa reflexão (spec mentoria_submentores,
 * RF-04 e D-07, etapa 1). Achou um, a resposta é a mensagem de cuidado, sem chamar a IA:
 * funciona com a IA desligada, fora do ar ou sem verba.
 *
 * Versão inicial de 2026-10-24, aguardando a revisão do Kayque. Mostrar cuidado sem precisar
 * não pune ninguém; deixar de mostrar quando precisa é o risco grave. Por isso a lista erra
 * para o lado do cuidado, e os alarmes falsos aceitos ficam escritos no teste.
 */
final class CareSignals
{
    /**
     * Os padrões rodam sobre o texto sem acento e em minúsculas.
     *
     * @var array<string, string>
     */
    public const PATTERNS = [
        // "me matar de rir", "me matei de trabalhar" e afins ficam de fora.
        'se matar' => '/\b(me|se) mat(ar|o|ou|ei|aria|asse)\b(?! de \w+(ar|er|ir)\b)/u',
        'suicídio' => '/\bsuicid/u',
        'tirar a própria vida' => '/\btirar (a )?(minha|propria) vida\b/u',
        'acabar com a vida' => '/\bacabar com (a )?minha vida\b/u',
        'acabar com tudo' => '/\bacabar com tudo\b/u',
        'não querer viver' => '/\bnao (quero|aguento|consigo|vejo sentido em) (mais )?viver\b/u',
        'cansaço de viver' => '/\b(cansei|cansad[oa]) de viver\b/u',
        'sem razão para viver' => '/\b(sem|nao tenho( mais)?|nao tem|nenhum|nenhuma) (razao|motivo|vontade|sentido) (pra|para|de|em) viver\b/u',
        // "vou morrer de vergonha", "de rir", "de trabalhar" e afins ficam de fora.
        'querer morrer' => '/\b(quero|queria|vontade de|vou|preferia) morrer\b(?! de (\w+(ar|er|ir)|vergonha|saudade|fome|sono|calor|frio|tedio|medo|cansaco|amor|inveja|curiosidade)\b)/u',
        'melhor morrer' => '/\bmelhor (eu )?(morto|morta|morrer)\b|\bse eu morresse\b/u',
        'melhor sem mim' => '/\b(melhor|mundo|todos|todo mundo) sem mim\b/u',
        'ninguém sentiria falta' => '/\bninguem (vai )?sentir(ia)? (a )?minha falta\b/u',
        'machucar a si' => '/\bme (machucar|machuco|machuquei|cortar|corto|cortei|ferir|firo|feri)\b/u',
        'automutilação' => '/\b(automutila|autolesa|auto-mutila|auto-lesa)/u',
        'sumir para sempre' => '/\b(sumir|desaparecer) (pra|para) sempre\b/u',
        'não acordar mais' => '/\b(dormir e nao acordar|nao acordar mais)\b/u',
        'sem saída' => '/\bnao (vejo|tem|ha|existe) (nenhuma )?saida\b/u',
        'desistir de viver' => '/\bdesist(i|ir|indo) de (viver|tudo|mim)\b/u',
        'se jogar' => '/\bme jogar (da|do|de|na frente|embaixo)\b/u',
        'violência' => '/\b(ele|ela) me (bate|bateu|agride|agrediu|espanca|espancou|ameaca|ameacou)\b|\bestuprad|\bfui abusad/u',
    ];

    /**
     * Os sinais que aparecem no texto, na ordem da lista.
     *
     * @return list<string>
     */
    public static function signalsIn(string $text): array
    {
        $normalized = self::normalize($text);

        return array_keys(array_filter(
            self::PATTERNS,
            fn (string $pattern) => preg_match($pattern, $normalized) === 1,
        ));
    }

    public static function found(string $text): bool
    {
        return self::signalsIn($text) !== [];
    }

    private static function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($text)));
    }
}
