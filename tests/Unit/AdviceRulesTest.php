<?php

namespace Tests\Unit;

use App\Support\AdviceRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdviceRulesTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function advice(): array
    {
        return [
            'remédio' => ['Que tal tomar um remédio para dormir, :codinome?', 'remédio'],
            'medicamento' => ['Converse sobre medicação com alguém.', 'medicamento'],
            'dose' => ['Uma dose de calma resolve.', 'dose'],
            'calmante' => ['Um calmante natural pode ajudar.', 'calmante'],
            'suplemento' => ['Um suplemento pode dar energia.', 'suplemento'],
            'diagnóstico' => ['Isso parece um diagnóstico de cansaço.', 'diagnóstico'],
            'tratamento' => ['Vale começar um tratamento.', 'tratamento'],
            'transtorno, sem acento' => ['Isso pode ser depressao.', 'transtorno'],
            'investimento' => ['Invista em você e no seu futuro.', 'investimento'],
            'investir' => ['Agora é hora de investir o que sobrou.', 'investimento'],
            'ações da bolsa' => ['Pense em comprar ações da bolsa.', 'ações da bolsa'],
            'cripto' => ['Bitcoin pode ser uma saída.', 'cripto'],
            'renda fixa' => ['Coloque no Tesouro Direto.', 'renda fixa'],
            'poupança' => ['Guarde na poupança.', 'poupança'],
            'empréstimo' => ['Um empréstimo resolveria o aperto.', 'empréstimo'],
        ];
    }

    #[DataProvider('advice')]
    public function test_rn09_conselho_profissional_e_reconhecido(string $reply, string $term): void
    {
        $this->assertContains($term, AdviceRules::termsIn($reply), $reply);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ordinaryReplies(): array
    {
        return [
            'pequenas ações' => ['Pequenas ações, repetidas, montam a rotina.'],
            'escudo e reserva' => ['Cada peça do escudo conta, e a reserva cresce aos poucos.'],
            'dos e das' => ['Cuidar dos dias e das noites também é missão.'],
            'olhar para o dinheiro' => ['Olhar para o dinheiro sem medo já é um passo.'],
            'conversar com alguém' => ['Conversar com alguém de confiança pode ajudar.'],
        ];
    }

    #[DataProvider('ordinaryReplies')]
    public function test_rn09_resposta_comum_passa(string $reply): void
    {
        $this->assertSame([], AdviceRules::termsIn($reply), $reply);
    }
}
