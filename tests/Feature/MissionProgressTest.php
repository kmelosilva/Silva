<?php

namespace Tests\Feature;

use App\Models\Coordinate;
use App\Models\CoordinateCompletion;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMission;
use App\Support\AgentDay;
use Carbon\CarbonImmutable;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MissionProgressTest extends TestCase
{
    use RefreshDatabase;

    // "Corpo são, mente sã": a missão do catálogo com 3 coordenadas.
    private const MISSION = 'corpo-sao-mente-sa';

    private const FIRST = 'leia-dez-paginas';

    private const SECOND = 'pausa-de-cinco-minutos';

    private const THIRD = 'escreva-quem-voce-e-hoje';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
    }

    public function test_rf03_aceitar_registra_em_andamento_e_mostra_a_primeira_coordenada(): void
    {
        $user = $this->signIn();

        $this->accept(self::MISSION)
            ->assertCreated()
            ->assertJsonPath('data.mission_slug', self::MISSION)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.completed_coordinates', [])
            ->assertJsonPath('data.next_coordinate', self::FIRST)
            ->assertJsonPath('data.completed_at', null);

        $this->assertNotNull(UserMission::where('user_id', $user->id)->sole()->accepted_at);
    }

    public function test_rf03_aceitar_de_novo_diz_que_ja_esta_em_andamento_e_nao_cria_outro_registro(): void
    {
        $this->signIn();
        $this->accept(self::MISSION)->assertCreated();

        $this->accept(self::MISSION)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'mission_already_accepted')
            ->assertJsonPath('error.message', 'Esta missão já está em andamento.');

        $this->assertSame(1, UserMission::count());
    }

    public function test_rf03_aceitar_missao_concluida_diz_que_ja_foi_concluida(): void
    {
        $this->signIn();
        $this->accept('nao-espere-o-sol');
        $this->complete('nao-espere-o-sol', 'acorde-meia-hora-antes')->assertOk();

        $this->accept('nao-espere-o-sol')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'mission_already_completed')
            ->assertJsonPath('error.message', 'Você já concluiu esta missão.');

        $this->assertSame(1, UserMission::count());
    }

    #[DataProvider('missionsOutsideTheModules')]
    public function test_rf03_missao_fora_dos_modulos_nao_pode_ser_aceita(string $slug): void
    {
        $retired = Mission::factory()->create(['slug' => 'aposentada', 'retired_at' => now()]);
        Coordinate::factory()->create(['mission_id' => $retired->id]);
        $this->signIn();

        $this->accept($slug)->assertNotFound()->assertJsonPath('message', 'Missão não encontrada.');

        $this->assertSame(0, UserMission::count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missionsOutsideTheModules(): array
    {
        return [
            'de entrada' => ['seja-um-agente'],
            'aposentada' => ['aposentada'],
            'inexistente' => ['nao-existe'],
        ];
    }

    public function test_rf04_cumprir_a_proxima_soma_o_xp_e_mostra_a_seguinte(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);

        $this->complete(self::MISSION, self::FIRST)
            ->assertOk()
            ->assertJsonPath('data.result.xp_gained', 20)
            ->assertJsonPath('data.result.already_completed', false)
            ->assertJsonPath('data.progress.completed_coordinates', [self::FIRST])
            ->assertJsonPath('data.progress.next_coordinate', self::SECOND)
            ->assertJsonPath('data.agent.xp', 20);

        $this->assertSame(20, $user->agent->fresh()->xp);
    }

    public function test_rf04_fora_de_ordem_e_recusado_e_diz_qual_e_a_proxima(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);

        $this->complete(self::MISSION, self::THIRD)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'coordinate_out_of_order')
            ->assertJsonPath('error.message', 'Cumpra antes a coordenada 1: Leia dez páginas de um livro.')
            ->assertJsonPath('error.next_coordinate', self::FIRST);

        $this->complete(self::MISSION, self::FIRST)->assertOk();

        $this->complete(self::MISSION, self::THIRD)
            ->assertUnprocessable()
            ->assertJsonPath('error.next_coordinate', self::SECOND);

        $this->assertSame(20, $user->agent->fresh()->xp);
        $this->assertSame(1, CoordinateCompletion::count());
    }

    public function test_rf04_sem_aceitar_e_recusado_e_oferece_aceitar(): void
    {
        $user = $this->signIn();

        $this->complete(self::MISSION, self::FIRST)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'mission_not_accepted')
            ->assertJsonPath('error.message', 'Aceite a missão para começar a cumprir as coordenadas.');

        $this->assertSame(0, $user->agent->fresh()->xp);
        $this->assertSame(0, CoordinateCompletion::count());
    }

    public function test_rf04_coordenada_de_outra_missao_ou_sem_texto_da_404(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        Coordinate::where('slug', self::THIRD)->update(['content' => null]);

        $this->complete(self::MISSION, 'acorde-meia-hora-antes')
            ->assertNotFound()
            ->assertJsonPath('message', 'Coordenada não encontrada.');
        $this->complete(self::MISSION, self::THIRD)->assertNotFound();

        $this->assertSame(0, CoordinateCompletion::count());
    }

    public function test_rf05_missao_de_entrada_e_cumprida_depois_do_alistamento(): void
    {
        $this->signIn();
        $this->postJson('/api/enlistment/oath')->assertOk();
        $this->postJson('/api/enlistment/codename', ['name' => 'Ana'])->assertOk();
        $this->postJson('/api/enlistment/complete', ['priorities' => []])->assertOk();

        $this->complete('seja-um-agente', 'siga-o-codigo-de-etica')
            ->assertOk()
            ->assertJsonPath('data.result.xp_gained', 20)
            ->assertJsonPath('data.result.mission_completed', true)
            ->assertJsonPath('data.progress.status', 'completed')
            ->assertJsonPath('data.agent.missions_completed', 1);
    }

    public function test_rf05_cumprir_a_entrada_sem_o_alistamento_pede_para_aceitar(): void
    {
        $this->signIn();

        $this->complete('seja-um-agente', 'siga-o-codigo-de-etica')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'mission_not_accepted');

        $this->assertSame(0, CoordinateCompletion::count());
    }

    public function test_rf05_a_tela_busca_a_missao_de_entrada_fora_dos_modulos(): void
    {
        $this->signIn();

        $this->getJson('/api/missions/entry')
            ->assertOk()
            ->assertJsonPath('data.slug', 'seja-um-agente')
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.coordinates_count', 1)
            ->assertJsonPath('data.coordinates.0.slug', 'siga-o-codigo-de-etica');
        $this->assertSame(1, Mission::entry()->count());

        Coordinate::where('slug', 'siga-o-codigo-de-etica')->update(['content' => null]);

        $this->getJson('/api/missions/entry')->assertNotFound()->assertJsonPath('message', 'Missão não encontrada.');
    }

    public function test_rnf01_envio_duplicado_soma_o_xp_uma_unica_vez(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);

        $this->complete(self::MISSION, self::FIRST)->assertOk()->assertJsonPath('data.result.xp_gained', 20);

        $this->complete(self::MISSION, self::FIRST)
            ->assertOk()
            ->assertJsonPath('data.result.xp_gained', 0)
            ->assertJsonPath('data.result.already_completed', true)
            ->assertJsonPath('data.progress.next_coordinate', self::SECOND)
            ->assertJsonPath('data.agent.xp', 20);

        // Somou, e somou uma vez: nem 0 nem 40.
        $this->assertSame(20, $user->agent->fresh()->xp);
        $this->assertSame(1, CoordinateCompletion::count());
    }

    public function test_rf05_ultima_coordenada_conclui_a_missao_e_conta_no_painel(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);

        $this->complete(self::MISSION, self::FIRST)->assertJsonPath('data.result.mission_completed', false);
        $this->complete(self::MISSION, self::SECOND)->assertJsonPath('data.result.mission_completed', false);

        $this->complete(self::MISSION, self::THIRD)
            ->assertOk()
            ->assertJsonPath('data.result.mission_completed', true)
            ->assertJsonPath('data.progress.status', 'completed')
            ->assertJsonPath('data.progress.next_coordinate', null)
            ->assertJsonPath('data.progress.completed_coordinates', [self::FIRST, self::SECOND, self::THIRD])
            ->assertJsonPath('data.agent.missions_completed', 1);

        $this->getJson('/api/agent')->assertJsonPath('data.missions_completed', 1);
        $this->assertNotNull(UserMission::sole()->completed_at);
    }

    public function test_rf06_sobe_de_nivel_quando_o_xp_alcanca_a_curva(): void
    {
        $user = $this->signIn();
        $user->agent->update(['xp' => 40]);
        $this->accept('nao-espere-o-sol');

        $this->complete('nao-espere-o-sol', 'acorde-meia-hora-antes')
            ->assertJsonPath('data.result.levels_gained', 1)
            ->assertJsonPath('data.agent.level', 2)
            ->assertJsonPath('data.agent.xp', 60)
            ->assertJsonPath('data.agent.level_xp', 50)
            ->assertJsonPath('data.agent.next_level_xp', 150);
    }

    public function test_rf06_um_ganho_grande_sobe_varios_niveis_de_uma_vez(): void
    {
        $big = Mission::factory()->create(['slug' => 'grande']);
        Coordinate::factory()->create(['mission_id' => $big->id, 'slug' => 'unica', 'xp' => 300]);
        $user = $this->signIn();
        $this->accept('grande');

        $this->complete('grande', 'unica')
            ->assertJsonPath('data.result.levels_gained', 3)
            ->assertJsonPath('data.agent.level', 4);

        $this->assertSame(4, $user->agent->fresh()->level);
    }

    public function test_rf06_nivel_e_xp_nunca_descem(): void
    {
        // Como se a curva tivesse mudado: nível guardado acima do que a curva daria.
        $user = $this->signIn();
        $user->agent->update(['level' => 5, 'xp' => 0]);
        $this->accept('nao-espere-o-sol');

        $this->complete('nao-espere-o-sol', 'acorde-meia-hora-antes')
            ->assertJsonPath('data.result.levels_gained', 0)
            ->assertJsonPath('data.agent.level', 5)
            ->assertJsonPath('data.agent.xp', 20);
    }

    public function test_d02_xp_do_agente_e_a_soma_das_coordenadas_cumpridas(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);
        $this->accept('negociando-com-cronos');

        $this->complete(self::MISSION, self::FIRST);
        $this->complete('negociando-com-cronos', 'escolha-tres-prioridades');
        $this->complete(self::MISSION, self::SECOND);
        $this->complete(self::MISSION, self::SECOND);

        $this->assertSame(60, $user->agent->fresh()->xp);
        $this->assertSame(60, (int) CoordinateCompletion::where('user_id', $user->id)->sum('xp'));
    }

    public function test_d02_mudar_o_xp_no_catalogo_nao_mexe_no_que_ja_foi_ganho(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        Coordinate::where('slug', self::FIRST)->update(['xp' => 5]);

        $this->getJson('/api/agent')->assertJsonPath('data.xp', 20);
        $this->assertSame(20, CoordinateCompletion::sole()->xp);
        $this->assertSame(20, $user->agent->fresh()->xp);
    }

    public function test_rnf02_um_agente_nao_ve_nem_altera_o_progresso_de_outro(): void
    {
        $ana = $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $bia = $this->signIn();

        $this->getJson('/api/missions/my')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/agent')->assertJsonPath('data.xp', 0);
        $this->complete(self::MISSION, self::SECOND)->assertStatus(409);
        $this->accept(self::MISSION)->assertCreated()->assertJsonPath('data.next_coordinate', self::FIRST);

        $this->assertSame(20, $ana->agent->fresh()->xp);
        $this->assertSame(0, $bia->agent->fresh()->xp);
        $this->assertSame(1, CoordinateCompletion::count());
    }

    public function test_missoes_aceitas_vem_na_ordem_de_aceite_com_o_progresso_de_cada_uma(): void
    {
        $this->signIn();
        $this->accept('negociando-com-cronos');
        $this->travel(1)->minutes();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $this->getJson('/api/missions/my')
            ->assertOk()
            ->assertJsonPath('data.0.mission_slug', 'negociando-com-cronos')
            ->assertJsonPath('data.0.completed_coordinates', [])
            ->assertJsonPath('data.1.mission_slug', self::MISSION)
            ->assertJsonPath('data.1.completed_coordinates', [self::FIRST])
            ->assertJsonPath('data.1.next_coordinate', self::SECOND);
    }

    public function test_rf06_missao_aceita_ha_10_dias_sem_coordenada_cumprida_da_boas_vindas(): void
    {
        $this->signIn();
        $this->travelTo($this->brasilia('2026-10-14 09:00'));
        $this->accept(self::MISSION)->assertJsonPath('data.welcome_back', false);

        $this->travelTo($this->brasilia('2026-10-24 09:00'));

        $this->getJson('/api/missions/my')->assertOk()->assertJsonPath('data.0.welcome_back', true);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function daysSinceTheLastCoordinate(): array
    {
        return [
            'há 7 dias' => ['2026-10-17 09:00', true],
            'há 6 dias' => ['2026-10-18 09:00', false],
            'hoje' => ['2026-10-24 08:00', false],
        ];
    }

    #[DataProvider('daysSinceTheLastCoordinate')]
    public function test_rf06_conta_a_partir_da_ultima_coordenada_cumprida(string $completedAt, bool $welcomeBack): void
    {
        $this->signIn();
        $this->travelTo($this->brasilia('2026-10-01 09:00'));
        $this->accept(self::MISSION);
        $this->travelTo($this->brasilia($completedAt));
        $this->complete(self::MISSION, self::FIRST)->assertJsonPath('data.progress.welcome_back', false);

        $this->travelTo($this->brasilia('2026-10-24 09:00'));

        $this->getJson('/api/missions/my')->assertOk()->assertJsonPath('data.0.welcome_back', $welcomeBack);
    }

    public function test_rf06_missao_concluida_nao_da_boas_vindas(): void
    {
        $this->signIn();
        $this->travelTo($this->brasilia('2026-10-01 09:00'));
        $this->accept(self::MISSION);
        foreach ([self::FIRST, self::SECOND, self::THIRD] as $coordinate) {
            $this->complete(self::MISSION, $coordinate);
        }

        $this->travelTo($this->brasilia('2026-10-24 09:00'));

        $this->getJson('/api/missions/my')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.welcome_back', false);
    }

    public function test_rf06_o_prazo_conta_dias_de_brasilia_e_nao_de_utc(): void
    {
        $this->signIn();
        $this->travelTo($this->brasilia('2026-10-01 09:00'));
        $this->accept(self::MISSION);
        $this->accept('negociando-com-cronos');
        $this->travelTo($this->brasilia('2026-10-17 10:00'));
        $this->complete('negociando-com-cronos', 'escolha-tres-prioridades');
        $this->travelTo($this->brasilia('2026-10-18 10:00'));
        $this->complete(self::MISSION, self::FIRST);

        // 22h de 24/10 em Brasília já é 25/10 em UTC: pelo UTC, as duas dariam boas-vindas.
        $this->travelTo($this->brasilia('2026-10-24 22:00'));
        $progress = collect($this->getJson('/api/missions/my')->assertOk()->json('data'))->keyBy('mission_slug');

        $this->assertTrue($progress['negociando-com-cronos']['welcome_back'], '7 dias de Brasília');
        $this->assertFalse($progress[self::MISSION]['welcome_back'], '6 dias de Brasília, 7 de UTC');
    }

    public function test_contrato_do_progresso_tem_exatamente_os_campos_combinados(): void
    {
        $this->signIn();
        $progressFields = ['mission_slug', 'status', 'completed_coordinates', 'next_coordinate', 'accepted_at', 'completed_at', 'welcome_back'];

        $this->assertSame($progressFields, array_keys($this->accept(self::MISSION)->json('data')));
        $this->assertSame($progressFields, array_keys($this->getJson('/api/missions/my')->json('data.0')));

        $completion = $this->complete(self::MISSION, self::FIRST)->json('data');

        $this->assertSame(['progress', 'agent', 'result'], array_keys($completion));
        $this->assertSame($progressFields, array_keys($completion['progress']));
        $this->assertSame(
            ['xp_gained', 'levels_gained', 'mission_completed', 'already_completed'],
            array_keys($completion['result'])
        );
    }

    private function brasilia(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, AgentDay::TIMEZONE);
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function accept(string $mission): TestResponse
    {
        return $this->postJson("/api/missions/{$mission}/accept");
    }

    private function complete(string $mission, string $coordinate): TestResponse
    {
        return $this->postJson("/api/missions/{$mission}/coordinates/{$coordinate}/complete");
    }
}
