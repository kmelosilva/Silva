<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Coordenadas do catálogo, na ordem em que podem ser cumpridas.
     *
     * @var array<int, array{string, string}>
     */
    private const STEPS = [
        ['corpo-sao-mente-sa', 'leia-dez-paginas'],
        ['corpo-sao-mente-sa', 'pausa-de-cinco-minutos'],
        ['corpo-sao-mente-sa', 'escreva-quem-voce-e-hoje'],
        ['negociando-com-cronos', 'escolha-tres-prioridades'],
        ['negociando-com-cronos', 'transforme-um-desejo-em-meta'],
    ];

    private User $user;

    private int $step = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        $this->postJson('/api/missions/corpo-sao-mente-sa/accept')->assertCreated();
        $this->postJson('/api/missions/negociando-com-cronos/accept')->assertCreated();
    }

    public function test_rf07_a_primeira_coordenada_comeca_a_sequencia_em_1(): void
    {
        $this->at('2026-10-20 10:00');

        $this->completeNext()->assertJsonPath('data.agent.streak', ['current' => 1, 'best' => 1]);
    }

    public function test_rf07_o_dia_seguinte_soma_1_e_o_mesmo_dia_nao_soma(): void
    {
        $this->at('2026-10-20 10:00');
        $this->completeNext();
        $this->at('2026-10-20 18:00');
        $this->completeNext()->assertJsonPath('data.agent.streak.current', 1);

        $this->at('2026-10-21 09:00');
        $this->completeNext()->assertJsonPath('data.agent.streak', ['current' => 2, 'best' => 2]);

        $this->at('2026-10-21 22:00');
        $this->getJson('/api/agent')->assertJsonPath('data.streak.current', 2);
    }

    public function test_rf07_um_dia_sem_atividade_recomeca_a_sequencia_sem_tirar_xp_nem_nivel(): void
    {
        $this->at('2026-10-20 10:00');
        $this->completeNext();
        $this->at('2026-10-21 10:00');
        $this->completeNext();
        $this->at('2026-10-22 10:00');
        $this->completeNext();
        $before = $this->getJson('/api/agent')->json('data');

        // 23/10 passou em branco: em 24/10 a sequência aparece interrompida.
        $this->at('2026-10-24 10:00');
        $after = $this->getJson('/api/agent')->assertOk()->json('data');

        $this->assertSame(['current' => 0, 'best' => 3], $after['streak']);
        $this->assertSame($before['xp'], $after['xp']);
        $this->assertSame($before['level'], $after['level']);

        $this->completeNext()->assertJsonPath('data.agent.streak', ['current' => 1, 'best' => 3]);
    }

    public function test_rf07_ontem_ativo_ainda_conta_ate_o_fim_de_hoje(): void
    {
        $this->at('2026-10-20 10:00');
        $this->completeNext();

        $this->at('2026-10-21 23:59');
        $this->getJson('/api/agent')->assertJsonPath('data.streak.current', 1);
    }

    public function test_rf07_o_dia_e_contado_no_horario_de_brasilia(): void
    {
        // 23h30 de 20/10 em Brasília já é 21/10 em UTC.
        $this->at('2026-10-20 23:30');
        $this->completeNext();
        $this->assertSame('2026-10-20', $this->user->agent->fresh()->last_active_on);

        // 00h30 de 21/10 em Brasília: é outro dia aqui, embora em UTC seja o mesmo dia 21.
        $this->at('2026-10-21 00:30');
        $this->completeNext()->assertJsonPath('data.agent.streak.current', 2);
    }

    /**
     * Viaja para uma hora do horário de Brasília.
     */
    private function at(string $brasilia): void
    {
        $this->travelTo(CarbonImmutable::parse($brasilia, 'America/Sao_Paulo')->utc());
    }

    private function completeNext(): \Illuminate\Testing\TestResponse
    {
        [$mission, $coordinate] = self::STEPS[$this->step++];

        return $this->postJson("/api/missions/{$mission}/coordinates/{$coordinate}/complete")
            ->assertOk()
            ->assertJsonPath('data.result.xp_gained', 20);
    }
}
