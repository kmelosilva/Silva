<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailySuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_rf08_responde_sem_erro_para_quem_esta_conectado(): void
    {
        $this->getJson('/api/missions/today')->assertOk();
    }

    public function test_rf08_com_missao_em_andamento_sugere_a_proxima_coordenada_dela(): void
    {
        $this->accept('corpo-sao-mente-sa');
        $this->complete('corpo-sao-mente-sa', 'leia-dez-paginas');

        $this->getJson('/api/missions/today')
            ->assertOk()
            ->assertJsonPath('data.type', 'next_coordinate')
            ->assertJsonPath('data.mission.slug', 'corpo-sao-mente-sa')
            ->assertJsonPath('data.mission.category', ['key' => 'mente', 'label' => 'Mente'])
            ->assertJsonPath('data.coordinate.slug', 'pausa-de-cinco-minutos')
            ->assertJsonPath('data.coordinate.title', 'Faça uma pausa de cinco minutos em silêncio');
    }

    public function test_rf08_com_varias_em_andamento_sugere_a_aceita_ha_mais_tempo(): void
    {
        $this->accept('negociando-com-cronos');
        $this->travel(1)->minutes();
        $this->accept('corpo-sao-mente-sa');

        $this->getJson('/api/missions/today')
            ->assertJsonPath('data.mission.slug', 'negociando-com-cronos')
            ->assertJsonPath('data.coordinate.slug', 'escolha-tres-prioridades');
    }

    public function test_rf08_sem_missao_em_andamento_sugere_uma_missao_dos_modulos_ainda_nao_aceita(): void
    {
        $this->accept('nao-espere-o-sol');
        $this->complete('nao-espere-o-sol', 'acorde-meia-hora-antes');

        $response = $this->getJson('/api/missions/today')
            ->assertOk()
            ->assertJsonPath('data.type', 'suggested_mission')
            ->assertJsonPath('data.coordinate', null);

        $slug = $response->json('data.mission.slug');
        $this->assertContains($slug, Mission::inModules()->pluck('slug')->all());
        $this->assertNotSame('nao-espere-o-sol', $slug);
    }

    public function test_rf08_a_sugestao_e_a_mesma_o_dia_todo_e_nao_grava_nada(): void
    {
        $before = $this->rowCounts();
        $slugs = [];

        foreach (['2026-10-20 00:10', '2026-10-20 12:00', '2026-10-20 23:50'] as $time) {
            $this->travelTo(CarbonImmutable::parse($time, 'America/Sao_Paulo'));
            $slugs[] = $this->getJson('/api/missions/today')->assertOk()->json('data.mission.slug');
        }

        $this->assertCount(1, array_unique($slugs));
        $this->assertNotNull($slugs[0]);
        $this->assertSame($before, $this->rowCounts());
    }

    public function test_rf08_respeita_o_nivel_minimo(): void
    {
        $this->ruleForAllExcept('gerindo-recursos', 'daily', minLevel: 5);

        $this->getJson('/api/missions/today')->assertJsonPath('data.mission.slug', 'gerindo-recursos');

        $this->user->agent->update(['level' => 5]);
        $this->accept('gerindo-recursos');
        $this->complete('gerindo-recursos', 'mapeie-entradas-e-saidas');

        $this->getJson('/api/missions/today')->assertJsonPath('data.type', 'suggested_mission');
    }

    public function test_rf08_missoes_semanais_e_de_evento_ficam_de_fora(): void
    {
        $this->ruleForAllExcept('celebre-a-vida', 'weekly');
        MissionRule::create(['mission_id' => $this->missionId('celebre-a-vida'), 'type' => 'event']);

        $this->getJson('/api/missions/today')->assertJsonPath('data.type', 'none');

        MissionRule::create(['mission_id' => $this->missionId('celebre-a-vida'), 'type' => 'random']);

        $this->getJson('/api/missions/today')->assertJsonPath('data.mission.slug', 'celebre-a-vida');
    }

    public function test_rf08_regra_fora_da_janela_de_datas_fica_de_fora(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'America/Sao_Paulo'));
        $this->ruleForAllExcept('nova-realidade', 'daily', minLevel: 99);
        MissionRule::create([
            'mission_id' => $this->missionId('nova-realidade'),
            'type' => 'daily',
            'start_date' => '2026-10-21',
        ]);

        $this->getJson('/api/missions/today')->assertJsonPath('data.type', 'none');

        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'America/Sao_Paulo'));

        $this->getJson('/api/missions/today')->assertJsonPath('data.mission.slug', 'nova-realidade');
    }

    public function test_rf08_missao_em_andamento_que_saiu_do_catalogo_nao_e_sugerida(): void
    {
        $this->accept('corpo-sao-mente-sa');
        Mission::where('slug', 'corpo-sao-mente-sa')->update(['retired_at' => now()]);

        $this->getJson('/api/missions/today')
            ->assertJsonPath('data.type', 'suggested_mission')
            ->assertJsonMissingPath('data.coordinate.slug');
    }

    public function test_rf08_quem_concluiu_tudo_nao_recebe_sugestao(): void
    {
        foreach (Mission::inModules()->with('publishedCoordinates')->get() as $mission) {
            $this->accept($mission->slug);

            foreach ($mission->publishedCoordinates as $coordinate) {
                $this->complete($mission->slug, $coordinate->slug);
            }
        }

        $this->getJson('/api/missions/today')
            ->assertOk()
            ->assertJsonPath('data.type', 'none')
            ->assertJsonPath('data.mission', null)
            ->assertJsonPath('data.coordinate', null);
        $this->getJson('/api/agent')->assertJsonPath('data.xp', 200)->assertJsonPath('data.level', 3);
    }

    public function test_contrato_da_sugestao_tem_exatamente_os_campos_combinados(): void
    {
        $suggested = $this->getJson('/api/missions/today')->json('data');

        $this->assertSame(['type', 'mission', 'coordinate'], array_keys($suggested));
        $this->assertSame(['slug', 'title', 'category'], array_keys($suggested['mission']));

        $this->accept('corpo-sao-mente-sa');
        $next = $this->getJson('/api/missions/today')->json('data');

        $this->assertSame(['slug', 'position', 'title'], array_keys($next['coordinate']));
    }

    private function accept(string $mission): void
    {
        $this->postJson("/api/missions/{$mission}/accept")->assertCreated();
    }

    private function complete(string $mission, string $coordinate): void
    {
        $this->postJson("/api/missions/{$mission}/coordinates/{$coordinate}/complete")->assertOk();
    }

    private function missionId(string $slug): int
    {
        return Mission::where('slug', $slug)->value('id');
    }

    private function ruleForAllExcept(string $slug, string $type, int $minLevel = 1): void
    {
        foreach (Mission::inModules()->where('slug', '!=', $slug)->pluck('id') as $id) {
            MissionRule::create(['mission_id' => $id, 'type' => $type, 'min_level' => $minLevel]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return collect(['agents', 'user_missions', 'coordinate_completions', 'mission_rules', 'missions'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }
}
