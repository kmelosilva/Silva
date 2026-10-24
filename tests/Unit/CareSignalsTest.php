<?php

namespace Tests\Unit;

use App\Support\CareSignals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CareSignalsTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function riskReflections(): array
    {
        return [
            'quer se matar' => ['Hoje pensei em me matar.'],
            'sem acento e em maiúsculas' => ['NAO QUERO MAIS VIVER'],
            'suicídio' => ['Ando pensando em suicídio.'],
            'alguém próximo' => ['Meu irmão se matou no ano passado e hoje doeu de novo.'],
            'tirar a própria vida' => ['Às vezes penso em tirar a minha vida.'],
            'acabar com a vida' => ['Quero acabar com a minha vida.'],
            'querer morrer' => ['Eu queria morrer, sinceramente.'],
            'vontade de morrer' => ['Bateu uma vontade de morrer.'],
            'melhor sem mim' => ['O mundo seria melhor sem mim.'],
            'melhor morrer' => ['Seria melhor se eu morresse.'],
            'ninguém sentiria falta' => ['Ninguém sentiria a minha falta.'],
            'se machucar' => ['Ontem eu me cortei de novo.'],
            'automutilação' => ['Voltei com a automutilação.'],
            'sumir para sempre' => ['Queria sumir pra sempre.'],
            'não acordar mais' => ['Queria dormir e não acordar.'],
            'sem saída' => ['Não vejo nenhuma saída.'],
            'desistir de viver' => ['Estou desistindo de viver.'],
            'cansaço de viver' => ['Cansei de viver assim.'],
            'sem razão para viver' => ['Não tenho motivo pra viver.'],
            'se jogar' => ['Pensei em me jogar da ponte.'],
            'violência' => ['Ele me bateu de novo ontem.'],
            'quebra de linha no meio' => ["não quero\nmais viver"],
        ];
    }

    #[DataProvider('riskReflections')]
    public function test_rf04_sinal_de_risco_e_reconhecido(string $reflection): void
    {
        $this->assertTrue(CareSignals::found($reflection), $reflection);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everydayReflections(): array
    {
        return [
            'dia pesado' => ['Foi um dia pesado, mas cumpri.'],
            'não rendeu' => ['Hoje não rendeu muito, amanhã tento de novo.'],
            'morri de rir' => ['Li o capítulo e morri de rir.'],
            'me matar de rir' => ['Esse livro vai me matar de rir.'],
            'me matei de trabalhar' => ['Me matei de trabalhar essa semana.'],
            'morrer de vergonha' => ['Achei que ia morrer de vergonha, mas fui.'],
            'vou morrer de estudar' => ['Vou morrer de estudar até a prova.'],
            'matar a saudade' => ['Liguei para a minha avó para matar a saudade.'],
            'cansaço' => ['Cansei, mas fiz os 10 minutos.'],
            'não aguento mais o trânsito' => ['Não aguento mais o trânsito daqui.'],
            'cortar gastos' => ['Consegui cortar gastos com delivery.'],
            'saída do trabalho' => ['Fiz a pausa na saída do trabalho.'],
            'jogar bola' => ['Fui jogar bola com a turma.'],
        ];
    }

    #[DataProvider('everydayReflections')]
    public function test_rf04_frase_do_dia_a_dia_nao_dispara(string $reflection): void
    {
        $this->assertSame([], CareSignals::signalsIn($reflection), $reflection);
    }

    /**
     * Alarmes falsos aceitos (D-07): a lista erra para o lado do cuidado. Mostrar a mensagem
     * de cuidado sem precisar não pune ninguém; deixar de mostrar quando precisa é o risco grave.
     *
     * @return array<string, array{string}>
     */
    public static function acceptedFalseAlarms(): array
    {
        return [
            'acabar com tudo, no sentido de terminar' => ['Hoje vou acabar com tudo que ficou pendente.'],
            'vou morrer, como exagero' => ['Se eu não entregar isso amanhã, eu vou morrer.'],
            'desisti de tudo, no sentido de largar' => ['Desisti de tudo que não era prioridade.'],
        ];
    }

    #[DataProvider('acceptedFalseAlarms')]
    public function test_d07_alarmes_falsos_aceitos_mostram_o_cuidado(string $reflection): void
    {
        $this->assertTrue(CareSignals::found($reflection), $reflection);
    }
}
