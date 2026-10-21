<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMission;
use App\Support\ContentRules;
use App\Support\EnlistmentText;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnlistmentTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRY = 'seja-um-agente';

    private const ENTRY_COORDINATE = 'siga-o-codigo-de-etica';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
    }

    public function test_rf02_alistamento_comeca_no_juramento_com_o_nome_da_conta(): void
    {
        $this->signIn('Kayque Melo');

        $this->getJson('/api/enlistment')
            ->assertOk()
            ->assertJsonPath('data.step', 'oath')
            ->assertJsonPath('data.oath', 'Eu, Kayque Melo, aceito seguir em desenvolvimento pelo meu bem e pelo bem de todos perante o código de ética e conduta da Silva, e assim, cumprir as missões da minha jornada.')
            ->assertJsonPath('data.oath_accepted_at', null)
            ->assertJsonPath('data.suggested_name', 'Kayque')
            ->assertJsonPath('data.codename_suffix', 'da Silva');
    }

    public function test_rn08_o_codigo_de_etica_tem_de_5_a_7_itens_e_vem_com_o_juramento(): void
    {
        $this->signIn();

        $code = $this->getJson('/api/enlistment')->assertOk()->json('data.code_of_conduct');

        $this->assertGreaterThanOrEqual(5, count($code));
        $this->assertLessThanOrEqual(7, count($code));
        $this->assertSame(EnlistmentText::codeOfConduct(), $code);
        foreach ($code as $item) {
            $this->assertSame(['title', 'text'], array_keys($item));
            $this->assertNotSame('', $item['title']);
            $this->assertNotSame('', $item['text']);
        }
    }

    public function test_rf02_aceitar_o_juramento_guarda_a_data_e_aceitar_de_novo_mantem_a_primeira(): void
    {
        $user = $this->signIn();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
        $this->postJson('/api/enlistment/oath')
            ->assertOk()
            ->assertJsonPath('data.step', 'codename')
            ->assertJsonPath('data.oath_accepted_at', '2026-10-20T10:00:00.000000Z');

        $this->travelTo(now()->addHour());
        $this->postJson('/api/enlistment/oath')
            ->assertOk()
            ->assertJsonPath('data.oath_accepted_at', '2026-10-20T10:00:00.000000Z');

        $this->assertSame('2026-10-20 10:00:00', $user->agent()->sole()->oath_accepted_at->toDateTimeString());
    }

    public function test_rf02_sem_juramento_nao_ha_codinome(): void
    {
        $user = $this->signIn();

        $this->codename('Kayque')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'oath_required')
            ->assertJsonPath('error.message', 'Aceite o juramento para seguir com o alistamento.');

        $this->assertNull($user->agent()->sole()->codename);
    }

    public function test_rf03_codinome_e_o_nome_escolhido_seguido_de_da_silva(): void
    {
        $user = $this->signIn('Kayque Melo');
        $this->postJson('/api/enlistment/oath')->assertOk();

        $this->codename('Kay')
            ->assertOk()
            ->assertJsonPath('data.step', 'priorities')
            ->assertJsonPath('data.codename', 'Kay da Silva');

        $this->codename('Kay da Silva')->assertOk()->assertJsonPath('data.codename', 'Kay da Silva');
        $this->assertSame('Kay da Silva', $user->agent()->sole()->codename);
    }

    #[DataProvider('invalidNames')]
    public function test_rf03_codinome_invalido_e_recusado_em_portugues(mixed $name, string $message): void
    {
        $this->signIn();
        $this->postJson('/api/enlistment/oath')->assertOk();

        $this->codename($name)
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', $message);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidNames(): array
    {
        $onlyLetters = 'Use só letras no codinome (pode ter espaço, hífen e apóstrofo).';

        return [
            'vazio' => ['', 'Escreva o nome do seu codinome.'],
            'só o sufixo' => ['da Silva', 'Escreva o nome do seu codinome.'],
            'com número' => ['Kay1', $onlyLetters],
            'com símbolo' => ['<b>Kay</b>', $onlyLetters],
            'não é texto' => [['Kay'], $onlyLetters],
            'longo demais' => [str_repeat('a', 31), 'O codinome pode ter até 30 letras.'],
        ];
    }

    public function test_rf06_concluir_antes_do_juramento_ou_do_codinome_e_recusado(): void
    {
        $user = $this->signIn();

        $this->finish(['mente'])->assertStatus(409)->assertJsonPath('error.code', 'oath_required');

        $this->postJson('/api/enlistment/oath')->assertOk();
        $this->finish(['mente'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'codename_required')
            ->assertJsonPath('error.message', 'Escolha seu codinome para concluir o alistamento.');

        $this->assertNull($user->agent()->sole()->enlisted_at);
        $this->assertSame(0, UserMission::count());
    }

    public function test_rf05_concluir_grava_a_prioridade_e_poe_seja_um_agente_em_andamento(): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();

        $this->finish(['mente', 'habitos'])
            ->assertOk()
            ->assertJsonPath('data.step', 'done')
            ->assertJsonPath('data.priorities', [
                ['key' => 'mente', 'label' => 'Mente'],
                ['key' => 'habitos', 'label' => 'Hábitos'],
            ]);

        $agent = $user->agent()->sole();
        $this->assertNotNull($agent->enlisted_at);
        $this->assertEquals([Category::Mente, Category::Habitos], $agent->priorityAreas()->all());

        $this->getJson('/api/missions/my')
            ->assertOk()
            ->assertJsonPath('data.0.mission_slug', self::ENTRY)
            ->assertJsonPath('data.0.status', 'in_progress')
            ->assertJsonPath('data.0.next_coordinate', self::ENTRY_COORDINATE);
    }

    public function test_rn06_pular_a_prioridade_conclui_igual(): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();

        $this->finish([])->assertOk()->assertJsonPath('data.step', 'done')->assertJsonPath('data.priorities', []);

        $this->assertTrue($user->agent()->sole()->priorityAreas()->isEmpty());
        $this->assertSame(1, $user->missions()->count(), 'Pular a prioridade não pode tirar a missão de entrada.');
    }

    #[DataProvider('invalidPriorities')]
    public function test_rf04_prioridades_invalidas_sao_recusadas_em_portugues(array $body, string $field, string $message): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();

        // A chave do erro de um item tem ponto ("priorities.0"), então não dá para ler por caminho.
        $this->postJson('/api/enlistment/complete', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);

        $this->assertNull($user->agent()->sole()->enlisted_at);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPriorities(): array
    {
        return [
            'três áreas' => [['priorities' => ['mente', 'habitos', 'saude']], 'priorities', 'Escolha no máximo 2 áreas.'],
            'área repetida' => [['priorities' => ['mente', 'mente']], 'priorities.0', 'Cada área só pode ser escolhida uma vez.'],
            'área que não existe' => [['priorities' => ['corpo']], 'priorities.0', 'Essa área não existe.'],
            'sem o campo' => [[], 'priorities', 'Envie as áreas escolhidas, mesmo que nenhuma.'],
        ];
    }

    public function test_rf06_concluir_duas_vezes_conclui_uma_vez_so(): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();

        $first = $this->finish(['mente'])->assertOk()->json('data.enlisted_at');
        $this->travelTo(now()->addMinute());
        $this->finish(['saude', 'social'])
            ->assertOk()
            ->assertJsonPath('data.step', 'done')
            ->assertJsonPath('data.enlisted_at', $first)
            ->assertJsonPath('data.priorities', [['key' => 'mente', 'label' => 'Mente']]);

        $entry = Mission::where('slug', self::ENTRY)->firstOrFail();
        $this->assertSame(1, UserMission::where('user_id', $user->id)->where('mission_id', $entry->id)->count());
    }

    public function test_rf06_depois_de_concluido_juramento_e_codinome_sao_recusados(): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();
        $this->finish([])->assertOk();

        $this->postJson('/api/enlistment/oath')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'already_enlisted')
            ->assertJsonPath('error.message', 'Seu alistamento já foi concluído.');
        $this->codename('Outro')->assertStatus(409)->assertJsonPath('error.code', 'already_enlisted');

        $this->assertSame('Kayque da Silva', $user->agent()->sole()->codename);
        $this->getJson('/api/enlistment')->assertOk()->assertJsonPath('data.step', 'done');
    }

    public function test_rf06_alistamento_interrompido_retoma_no_passo_que_falta(): void
    {
        $this->signIn();
        $this->postJson('/api/enlistment/oath')->assertOk();

        $this->getJson('/api/enlistment')->assertOk()->assertJsonPath('data.step', 'codename');

        $this->codename('Kayque')->assertOk();
        $this->getJson('/api/enlistment')->assertOk()->assertJsonPath('data.step', 'priorities');
    }

    public function test_rf06_conta_antiga_com_progresso_e_convidada_e_nao_perde_nada(): void
    {
        $user = $this->signIn();
        $this->postJson('/api/missions/corpo-sao-mente-sa/accept')->assertCreated();
        $this->postJson('/api/missions/corpo-sao-mente-sa/coordinates/leia-dez-paginas/complete')->assertOk();

        $this->getJson('/api/enlistment')->assertOk()->assertJsonPath('data.step', 'oath');

        $this->enlistUntilPriorities();
        $this->finish(['mente'])->assertOk();

        $agent = $user->agent()->sole();
        $this->assertSame(20, $agent->xp);
        $this->assertSame(
            ['corpo-sao-mente-sa', self::ENTRY],
            $this->getJson('/api/missions/my')->json('data.*.mission_slug')
        );
        $this->getJson('/api/missions/my')->assertJsonPath('data.0.completed_coordinates', ['leia-dez-paginas']);
    }

    public function test_rf05_sem_missao_de_entrada_publicada_o_alistamento_conclui_sem_missao(): void
    {
        Coordinate::where('slug', self::ENTRY_COORDINATE)->update(['content' => null]);
        $user = $this->signIn();
        $this->enlistUntilPriorities();

        $this->finish(['mente'])->assertOk()->assertJsonPath('data.step', 'done');

        $this->assertSame(0, $user->missions()->count());
    }

    public function test_rnf03_alistamento_de_um_agente_nao_mexe_no_de_outro(): void
    {
        $other = User::factory()->create();
        $this->signIn();
        $this->enlistUntilPriorities();
        $this->finish(['mente'])->assertOk();

        $otherAgent = $other->agent()->sole();
        $this->assertNull($otherAgent->oath_accepted_at);
        $this->assertNull($otherAgent->codename);
        $this->assertNull($otherAgent->enlisted_at);
        $this->assertSame(0, $other->missions()->count());
    }

    public function test_rf04_mudar_a_prioridade_depois_devolve_o_painel(): void
    {
        $user = $this->signIn();
        $this->enlistUntilPriorities();
        $this->finish([])->assertOk();

        $this->putJson('/api/agent/priorities', ['priorities' => ['financas']])
            ->assertOk()
            ->assertJsonPath('data.priorities', [['key' => 'financas', 'label' => 'Finanças']])
            ->assertJsonPath('data.enlisted', true);

        $this->assertEquals([Category::Financas], $user->agent()->sole()->priorityAreas()->all());

        $this->putJson('/api/agent/priorities', ['priorities' => ['a', 'b', 'c']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.priorities.0', 'Escolha no máximo 2 áreas.');
    }

    public function test_rf04_areas_de_prioridade_sao_as_categorias_com_explicacao_e_missoes(): void
    {
        $this->signIn();

        $areas = $this->getJson('/api/priority-areas')->assertOk()->assertJsonPath('data.max', 2)->json('data.areas');

        $this->assertSame(array_column(Category::cases(), 'value'), array_column($areas, 'key'));
        $counts = array_column($areas, 'missions_count', 'key');
        $this->assertSame(0, $counts['saude'], 'Área sem missão também aparece, com 0.');
        $this->assertSame(Mission::inModules()->where('category', 'mente')->count(), $counts['mente']);
        $this->assertGreaterThan(0, $counts['mente']);
        foreach ($areas as $area) {
            $this->assertNotSame('', $area['description']);
        }
    }

    public function test_regra_saude_sem_peso_nos_textos_do_alistamento(): void
    {
        $texts = [
            EnlistmentText::oathFor('Ana'),
            ...array_map(fn (array $item) => $item['title'].' '.$item['text'], EnlistmentText::codeOfConduct()),
            ...array_map(fn (Category $category) => $category->description(), Category::cases()),
        ];

        foreach ($texts as $text) {
            $this->assertSame([], ContentRules::forbiddenTermsIn($text), "Termo proibido em: {$text}");
        }
    }

    public function test_contrato_do_alistamento_e_das_areas_tem_exatamente_os_campos_combinados(): void
    {
        $this->signIn();

        $enlistment = $this->getJson('/api/enlistment')->assertOk()->json('data');
        $this->assertSame(
            ['step', 'oath', 'code_of_conduct', 'oath_accepted_at', 'suggested_name', 'codename_suffix', 'codename', 'priorities', 'enlisted_at'],
            array_keys($enlistment)
        );

        $options = $this->getJson('/api/priority-areas')->assertOk()->json('data');
        $this->assertSame(['max', 'areas'], array_keys($options));
        $this->assertSame(['key', 'label', 'description', 'missions_count'], array_keys($options['areas'][0]));
    }

    #[DataProvider('enlistmentRoutes')]
    public function test_rotas_do_alistamento_pedem_login(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function enlistmentRoutes(): array
    {
        return [
            'estado' => ['GET', '/api/enlistment'],
            'juramento' => ['POST', '/api/enlistment/oath'],
            'codinome' => ['POST', '/api/enlistment/codename'],
            'concluir' => ['POST', '/api/enlistment/complete'],
            'mudar prioridade' => ['PUT', '/api/agent/priorities'],
            'áreas' => ['GET', '/api/priority-areas'],
        ];
    }

    private function signIn(string $name = 'Kayque Melo'): User
    {
        $user = User::factory()->create(['name' => $name]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function enlistUntilPriorities(): void
    {
        $this->postJson('/api/enlistment/oath')->assertOk();
        $this->codename('Kayque')->assertOk();
    }

    private function codename(mixed $name): TestResponse
    {
        return $this->postJson('/api/enlistment/codename', ['name' => $name]);
    }

    /**
     * @param  list<string>  $priorities
     */
    private function finish(array $priorities): TestResponse
    {
        return $this->postJson('/api/enlistment/complete', ['priorities' => $priorities]);
    }
}
