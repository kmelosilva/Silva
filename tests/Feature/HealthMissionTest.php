<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Models\User;
use App\Services\MissionCatalogSync;
use App\Support\ContentRules;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthMissionTest extends TestCase
{
    use RefreshDatabase;

    private const MISSION = 'corpo-sao';

    private const COORDINATES = [
        'mexa-o-corpo-do-seu-jeito',
        'faca-uma-refeicao-com-calma',
        'tenha-agua-por-perto',
        'prepare-uma-noite-de-sono',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
    }

    public function test_rf01_corpo_sao_e_uma_missao_de_saude_com_4_coordenadas_publicadas(): void
    {
        $mission = Mission::query()->inModules()->where('slug', self::MISSION)->firstOrFail();

        $this->assertSame('Corpo são', $mission->title);
        $this->assertSame(Category::Saude, $mission->category);
        $this->assertSame(self::COORDINATES, $mission->publishedCoordinates->pluck('slug')->all());
    }

    public function test_d01_mente_sa_e_a_mesma_missao_de_antes_com_o_mesmo_progresso(): void
    {
        // O catálogo como era antes da troca de título.
        $catalog = require database_path('data/mission_catalog.php');
        $index = array_search('corpo-sao-mente-sa', array_column($catalog, 'slug'), true);
        $before = $catalog;
        $before[$index]['title'] = 'Corpo são, mente sã';
        app(MissionCatalogSync::class)->sync($before);
        $missionId = Mission::where('slug', 'corpo-sao-mente-sa')->value('id');

        $this->signIn();
        $this->postJson('/api/missions/corpo-sao-mente-sa/accept')->assertCreated();
        $this->postJson('/api/missions/corpo-sao-mente-sa/coordinates/leia-dez-paginas/complete')->assertOk();

        app(MissionCatalogSync::class)->sync($catalog);

        $mission = Mission::where('slug', 'corpo-sao-mente-sa')->firstOrFail();
        $this->assertSame($missionId, $mission->id);
        $this->assertSame('Mente sã', $mission->title);
        $this->assertSame(Category::Mente, $mission->category);
        $this->assertNull($mission->retired_at);
        $this->getJson('/api/missions/my')
            ->assertOk()
            ->assertJsonPath('data.0.mission_slug', 'corpo-sao-mente-sa')
            ->assertJsonPath('data.0.completed_coordinates', ['leia-dez-paginas']);
        $this->getJson('/api/agent')->assertOk()->assertJsonPath('data.xp', 20);
    }

    public function test_rf03_toda_coordenada_fecha_com_a_versao_leve_e_cabe_em_um_minuto(): void
    {
        foreach ($this->coordinates() as $coordinate) {
            $paragraphs = explode("\n\n", $coordinate->content);
            $words = count(preg_split('/\s+/u', trim($coordinate->content)));

            $this->assertGreaterThanOrEqual(2, count($paragraphs), "{$coordinate->slug}: falta o texto antes da versão leve.");
            $this->assertStringStartsWith('Dia difícil?', end($paragraphs), "{$coordinate->slug}: precisa fechar com a versão leve.");
            $this->assertLessThanOrEqual(150, $words, "{$coordinate->slug}: {$words} palavras, mais de 1 minuto de leitura.");
        }
    }

    public function test_rn05_os_dados_citados_trazem_a_fonte(): void
    {
        $content = $this->coordinates()->pluck('content', 'slug');

        $this->assertStringContainsString(
            'Guia de Atividade Física para a População Brasileira, do Ministério da Saúde',
            $content['mexa-o-corpo-do-seu-jeito']
        );
        $this->assertStringContainsString(
            'Guia Alimentar para a População Brasileira, do Ministério da Saúde',
            $content['faca-uma-refeicao-com-calma']
        );
    }

    public function test_rf04_so_a_coordenada_de_movimento_traz_o_aviso_de_cuidado(): void
    {
        $this->signIn();

        $mission = collect($this->getJson('/api/missions')->assertOk()->json('data'))->firstWhere('slug', self::MISSION);
        $notices = array_column($mission['coordinates'], 'care_notice', 'slug');

        $this->assertSame([
            'mexa-o-corpo-do-seu-jeito' => Coordinate::CARE_NOTICE,
            'faca-uma-refeicao-com-calma' => null,
            'tenha-agua-por-perto' => null,
            'prepare-uma-noite-de-sono' => null,
        ], $notices);
    }

    public function test_rf04_o_aviso_fala_de_limites_e_de_profissional_sem_termo_proibido(): void
    {
        $this->assertStringContainsString('limites', Coordinate::CARE_NOTICE);
        $this->assertStringContainsString('profissional', Coordinate::CARE_NOTICE);
        $this->assertSame([], ContentRules::forbiddenTermsIn(Coordinate::CARE_NOTICE));
    }

    public function test_rnf01_nenhum_texto_carregado_usa_termo_proibido(): void
    {
        $texts = Mission::all()
            ->flatMap(fn (Mission $mission) => [$mission->title, $mission->description])
            ->merge(Coordinate::all()->flatMap(fn (Coordinate $coordinate) => [$coordinate->title, $coordinate->content]))
            ->filter();

        $this->assertGreaterThan(30, $texts->count(), 'A varredura precisa ter lido o catálogo.');
        foreach ($texts as $text) {
            $this->assertSame([], ContentRules::forbiddenTermsIn($text), "Termo proibido em: {$text}");
        }
    }

    public function test_rf05_quem_escolhe_saude_recebe_corpo_sao_depois_da_entrada(): void
    {
        $this->signIn();
        $this->postJson('/api/enlistment/oath')->assertOk();
        $this->postJson('/api/enlistment/codename', ['name' => 'Caren'])->assertOk();
        $this->postJson('/api/enlistment/complete', ['priorities' => ['saude']])->assertOk();
        $this->postJson('/api/missions/seja-um-agente/coordinates/siga-o-codigo-de-etica/complete')->assertOk();

        $this->getJson('/api/missions/today')
            ->assertOk()
            ->assertJsonPath('data.type', 'suggested_mission')
            ->assertJsonPath('data.mission.slug', self::MISSION)
            ->assertJsonPath('data.mission.category', ['key' => 'saude', 'label' => 'Saúde'])
            ->assertJsonPath('data.unavailable_priorities', []);
    }

    /**
     * @return Collection<int, Coordinate>
     */
    private function coordinates(): Collection
    {
        $coordinates = Mission::where('slug', self::MISSION)->firstOrFail()->publishedCoordinates;
        $this->assertCount(4, $coordinates);

        return $coordinates;
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
