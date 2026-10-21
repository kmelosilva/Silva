<?php

namespace App\Support;

/**
 * A regra "saúde sem peso" em forma de lista: termos que nenhum texto lido pelo agente usa
 * (catálogo, alistamento, avisos). A carga do catálogo recusa o texto que usar um deles.
 * Lista aprovada pelo Kayque em 2026-10-21 (spec conteudo_missoes-de-saude, seção 9).
 */
final class ContentRules
{
    /**
     * Cada termo com o padrão que pega o plural e as variações, sem pegar palavra de outro
     * sentido ("pesado", "quilômetro", "quilombo", "balançar").
     *
     * @var array<string, string>
     */
    public const FORBIDDEN_TERMS = [
        'peso' => '/\bpesos?\b/iu',
        'quilo' => '/\bquilo(s|gramas?)?\b/iu',
        'IMC' => '/\bimc\b/iu',
        'balança' => '/\bbalanças?\b/iu',
        'caloria' => '/\bcal[oó]ri/iu',
        'emagrecer' => '/\bemagre/iu',
        'dieta' => '/\bdietas?\b/iu',
        'queimar' => '/\bqueim/iu',
        'gordura' => '/\bgordur/iu',
        'besteira' => '/\bbesteiras?\b/iu',
        'proibido' => '/\bproibid/iu',
    ];

    /**
     * Os termos proibidos que aparecem no texto, na ordem da lista.
     *
     * @return list<string>
     */
    public static function forbiddenTermsIn(string $text): array
    {
        return array_keys(array_filter(
            self::FORBIDDEN_TERMS,
            fn (string $pattern) => preg_match($pattern, $text) === 1,
        ));
    }
}
