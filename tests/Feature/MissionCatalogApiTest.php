<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMission;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MissionCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
    }

    public function test_rnf04_sem_login_responde_401(): void
    {
        $this->getJson('/api/missions')->assertUnauthorized();
    }

    public function test_rf05_lista_as_7_missoes_dos_modulos_na_ordem_do_catalogo(): void
    {
        $this->signIn();

        $this->assertSame(
            [
                'nao-espere-o-sol',
                'corpo-sao-mente-sa',
                'luz-no-fim-do-tunel',
                'negociando-com-cronos',
                'gerindo-recursos',
                'nova-realidade',
                'celebre-a-vida',
            ],
            $this->getJson('/api/missions')->assertOk()->json('data.*.slug')
        );
    }

    public function test_rf01_coordenadas_em_ordem_e_xp_total_e_a_soma(): void
    {
        $this->signIn();

        $mission = $this->listed('corpo-sao-mente-sa');

        $this->assertSame(
            ['leia-dez-paginas', 'pausa-de-cinco-minutos', 'escreva-quem-voce-e-hoje'],
            array_column($mission['coordinates'], 'slug')
        );
        $this->assertSame([1, 2, 3], array_column($mission['coordinates'], 'position'));
        $this->assertSame(3, $mission['coordinates_count']);
        $this->assertSame(60, $mission['xp_total']);
    }

    public function test_rf04_coordenada_aposentada_sai_da_lista_e_do_xp_total(): void
    {
        $this->signIn();
        Coordinate::where('slug', 'escreva-quem-voce-e-hoje')->update(['retired_at' => now()]);

        $mission = $this->listed('corpo-sao-mente-sa');

        $this->assertSame(2, $mission['coordinates_count']);
        $this->assertSame(40, $mission['xp_total']);
    }

    public function test_rn06_missao_de_entrada_nao_aparece(): void
    {
        $this->signIn();

        $this->assertNotContains('seja-um-agente', $this->getJson('/api/missions')->json('data.*.slug'));
    }

    public function test_rf02_missao_gerada_some_depois_da_carga(): void
    {
        $this->signIn();
        $generated = Mission::factory()->generated()->has(Coordinate::factory())->create();
        $this->assertContains($generated->id, $this->listedIds(), 'Antes da carga, a missão gerada estaria visível.');

        $this->seed(MissionCatalogSeeder::class);

        $this->assertNotContains($generated->id, $this->listedIds());
    }

    public function test_rf01_missao_sem_coordenada_com_texto_nao_aparece(): void
    {
        $this->signIn();
        $mission = Mission::factory()->has(Coordinate::factory()->state(['content' => null]))->create();

        $this->assertNotContains($mission->id, $this->listedIds());

        $mission->coordinates()->update(['content' => 'Agora com texto.']);
        $this->assertContains($mission->id, $this->listedIds(), 'Com texto, a mesma missão aparece.');
    }

    public function test_rf04_missao_aposentada_nao_aparece(): void
    {
        $this->signIn();
        $cronos = Mission::where('slug', 'negociando-com-cronos')->firstOrFail();

        $cronos->update(['retired_at' => now()]);

        $this->assertNotContains($cronos->id, $this->listedIds());
    }

    public function test_rf03_toda_categoria_vem_da_lista_com_o_rotulo_dela(): void
    {
        $this->signIn();

        $categories = $this->getJson('/api/missions')->json('data.*.category');

        $this->assertNotEmpty($categories);
        foreach ($categories as $category) {
            $this->assertSame(Category::from($category['key'])->label(), $category['label']);
        }
        $this->assertSame(
            ['habitos', 'mente', 'carreira', 'financas'],
            array_values(array_unique(array_column($categories, 'key')))
        );
    }

    public function test_contrato_tem_exatamente_os_campos_combinados(): void
    {
        $this->signIn();

        $response = $this->getJson('/api/missions')->assertOk();
        $mission = $response->json('data.0');

        $this->assertSame(
            ['id', 'slug', 'title', 'description', 'category', 'coordinates_count', 'xp_total', 'coordinates'],
            array_keys($mission)
        );
        $this->assertSame(['key', 'label'], array_keys($mission['category']));
        $this->assertSame(['slug', 'position', 'title', 'content', 'xp'], array_keys($mission['coordinates'][0]));
    }

    public function test_rnf05_missoes_aceitas_continuam_respondendo_depois_da_carga(): void
    {
        $user = $this->signIn();
        $mission = Mission::where('slug', 'nao-espere-o-sol')->firstOrFail();
        UserMission::create(['user_id' => $user->id, 'mission_id' => $mission->id, 'accepted_at' => now()]);
        $this->seed(MissionCatalogSeeder::class);

        $this->getJson('/api/missions/my')->assertOk()->assertJsonCount(1, 'data');
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function listed(string $slug): array
    {
        $mission = collect($this->getJson('/api/missions')->assertOk()->json('data'))->firstWhere('slug', $slug);
        $this->assertNotNull($mission, "A missão {$slug} deveria estar na lista.");

        return $mission;
    }

    /**
     * @return array<int, int>
     */
    private function listedIds(): array
    {
        return $this->getJson('/api/missions')->assertOk()->json('data.*.id');
    }
}
