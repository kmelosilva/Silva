<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Conselho profissional que o submentor não dá (spec mentoria_submentores, RN-09 e D-07,
 * etapa 4): diagnóstico, remédio, tratamento, investimento e produto financeiro. A resposta
 * da IA que usar um destes termos é trocada pelo texto escrito do submentor.
 *
 * Versão inicial de 2026-10-24, aguardando a revisão do Kayque. Uma resposta boa que use
 * "remédio" em outro sentido também vira texto escrito: é o preço aceito (D-07).
 * "Ações" sozinha fica de fora, porque "pequenas ações" é o jeito comum de falar de passo.
 */
final class AdviceRules
{
    /**
     * Os padrões rodam sobre o texto sem acento e em minúsculas.
     *
     * @var array<string, string>
     */
    public const TERMS = [
        'remédio' => '/\bremedio/u',
        'medicamento' => '/\bmedica(mento|cao|r)/u',
        'dose' => '/\bdos(e|es|agem)\b/u',
        'comprimido' => '/\bcomprimido/u',
        'calmante' => '/\b(calmante|antidepressivo|ansiolitico)/u',
        'suplemento' => '/\bsuplement/u',
        'diagnóstico' => '/\bdiagnost/u',
        'tratamento' => '/\btratamento/u',
        'transtorno' => '/\b(transtorno|depressao|bipolar|tdah|sindrome)/u',
        'investimento' => '/\binv(est|ist)(e|a|am|ir|imento|imentos|ido|indo)\b/u',
        'ações da bolsa' => '/\b(bolsa de valores|comprar acoes|acoes (da|de|na) (bolsa|empresa))\b/u',
        'cripto' => '/\b(cripto|bitcoin)/u',
        'renda fixa' => '/\b(renda fixa|tesouro direto|cdb|lci|lca|fundo de investimento|previdencia privada)\b/u',
        'poupança' => '/\bpoupanca\b/u',
        'empréstimo' => '/\b(emprestimo|financiamento|consorcio|consignado)/u',
    ];

    /**
     * Os termos de conselho profissional que aparecem no texto, na ordem da lista.
     *
     * @return list<string>
     */
    public static function termsIn(string $text): array
    {
        $normalized = mb_strtolower(Str::ascii($text));

        return array_keys(array_filter(
            self::TERMS,
            fn (string $pattern) => preg_match($pattern, $normalized) === 1,
        ));
    }
}
