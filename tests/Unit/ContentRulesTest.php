<?php

namespace Tests\Unit;

use App\Support\ContentRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContentRulesTest extends TestCase
{
    public function test_rn01_a_lista_tem_os_11_termos_aprovados(): void
    {
        $this->assertSame(
            ['peso', 'quilo', 'IMC', 'balança', 'caloria', 'emagrecer', 'dieta', 'queimar', 'gordura', 'besteira', 'proibido'],
            array_keys(ContentRules::FORBIDDEN_TERMS)
        );
    }

    /**
     * @param  list<string>  $terms
     */
    #[DataProvider('forbidden')]
    public function test_rn01_pega_o_termo_o_plural_e_as_variacoes(string $text, array $terms): void
    {
        $this->assertSame($terms, ContentRules::forbiddenTermsIn($text));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function forbidden(): array
    {
        return [
            'peso' => ['O peso não importa', ['peso']],
            'pesos, maiúsculo' => ['PESOS E MEDIDAS', ['peso']],
            'quilos' => ['perder dois quilos', ['quilo']],
            'quilogramas' => ['em quilogramas', ['quilo']],
            'IMC' => ['calcule seu IMC', ['IMC']],
            'balança' => ['subir na balança', ['balança']],
            'calorias' => ['contar calorias', ['caloria']],
            'calórico' => ['um lanche calórico', ['caloria']],
            'emagrecimento' => ['Plano de emagrecimento', ['emagrecer']],
            'emagreça' => ['emagreça já', ['emagrecer']],
            'dieta' => ['Dieta da semana', ['dieta']],
            'queima' => ['queima rápida', ['queimar']],
            'gorduroso' => ['nada gorduroso', ['gordura']],
            'besteira' => ['comer besteira', ['besteira']],
            'proibida' => ['comida proibida', ['proibido']],
            'vários de uma vez' => ['queimar gordura sem dieta', ['dieta', 'queimar', 'gordura']],
        ];
    }

    #[DataProvider('allowed')]
    public function test_rn01_palavras_de_outro_sentido_passam(string $text): void
    {
        $this->assertSame([], ContentRules::forbiddenTermsIn($text));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowed(): array
    {
        return [
            'pesado' => ['Foi um dia pesado.'],
            'quilômetro' => ['Caminhe um quilômetro.'],
            'quilombo' => ['A história do quilombo.'],
            'balançar' => ['Balançar o corpo numa música.'],
            'queixo' => ['Erga o queixo.'],
            'pés' => ['Tire os sapatos e mexa os pés.'],
            'texto do catálogo' => ['Mexa o corpo do seu jeito por 10 minutos. Cada minuto conta.'],
        ];
    }
}
