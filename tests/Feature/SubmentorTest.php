<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Models\Agent;
use App\Models\Coordinate;
use App\Models\Mission;
use App\Models\User;
use App\Support\ContentRules;
use App\Support\MentoringText;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmentorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_login_responde_401(): void
    {
        $this->getJson('/api/submentors')->assertUnauthorized();
        $this->postJson('/api/submentors/saude/met')->assertUnauthorized();
    }

    public function test_rn03_lista_so_as_areas_com_missao_na_ordem_das_categorias(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $this->signIn();

        $this->getJson('/api/submentors')
            ->assertOk()
            ->assertJsonPath('data.*.area.key', ['saude', 'mente', 'financas', 'carreira', 'habitos'])
            ->assertJsonPath('data.*.codename', ['Pintada da Silva', 'Jabu da Silva', 'Tito da Silva', 'Guia da Silva', 'Bel da Silva'])
            ->assertJsonPath('data.*.met', [false, false, false, false, false]);
    }

    public function test_rn03_social_ganha_submentor_quando_a_area_ganha_missao(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $this->signIn();
        $mission = Mission::create(['slug' => 'roda-de-conversa', 'title' => 'Roda de conversa', 'category' => Category::Social, 'position' => 99]);
        Coordinate::create(['mission_id' => $mission->id, 'slug' => 'ligue-para-alguem', 'position' => 1, 'title' => 'Ligue para alguém', 'content' => 'Ligue para alguém de quem você sente falta.', 'xp' => 20]);

        $this->assertContains('Capi da Silva', $this->getJson('/api/submentors')->assertOk()->json('data.*.codename'));
    }

    public function test_rf01_conhecer_o_submentor_marca_a_area_e_repetir_mantem(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $user = $this->signIn();

        $this->postJson('/api/submentors/mente/met')
            ->assertOk()
            ->assertExactJson(['data' => ['area' => 'mente', 'met' => true]]);
        $this->postJson('/api/submentors/mente/met')->assertOk();
        $this->postJson('/api/submentors/saude/met')->assertOk();

        $listed = collect($this->getJson('/api/submentors')->assertOk()->json('data'))->pluck('met', 'area.key');
        $this->assertSame(['saude' => true, 'mente' => true, 'financas' => false, 'carreira' => false, 'habitos' => false], $listed->all());
        $this->assertSame(['mente', 'saude'], Agent::where('user_id', $user->id)->first()->metSubmentors()->pluck('value')->all(), 'Repetir não grava de novo.');
    }

    public function test_rn03_area_inexistente_ou_sem_missao_da_404(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $this->signIn();

        $this->postJson('/api/submentors/corpo/met')->assertNotFound();
        $this->postJson('/api/submentors/social/met')->assertNotFound();
    }

    public function test_rnf08_conta_antiga_sem_marca_nenhuma_funciona(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $user = $this->signIn();
        Agent::where('user_id', $user->id)->update(['submentors_met' => null]);

        $this->getJson('/api/submentors')->assertOk()->assertJsonPath('data.0.met', false);
        $this->postJson('/api/submentors/saude/met')->assertOk();
    }

    public function test_contrato_do_submentor_tem_exatamente_os_campos_combinados(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $this->signIn();

        $submentor = $this->getJson('/api/submentors')->assertOk()->json('data.0');

        $this->assertSame(
            ['area', 'codename', 'animal', 'power_up', 'intro', 'closing', 'welcome_backs', 'notice', 'care_footer', 'met'],
            array_keys($submentor)
        );
        $this->assertSame(['key', 'label'], array_keys($submentor['area']));
        $this->assertStringContainsString('Pintada da Silva', $submentor['notice']);
        $this->assertStringContainsString('188', $submentor['care_footer']);
    }

    public function test_rn01_cada_area_tem_um_submentor_da_silva(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (Category $area) => $area->value, Category::cases()),
            array_keys(MentoringText::content()['submentors'])
        );

        foreach (Category::cases() as $area) {
            $submentor = MentoringText::submentor($area);

            $this->assertStringEndsWith(' da Silva', $submentor['codename']);
            foreach (['animal', 'power_up', 'intro', 'voice', 'closing'] as $field) {
                $this->assertNotSame('', trim($submentor[$field]), "{$area->value}.{$field} vazio");
            }
            $this->assertCount(3, $submentor['written_replies'], "{$area->value}: respostas escritas");
        }
    }

    public function test_rn08_o_que_o_agente_le_nao_usa_termo_proibido(): void
    {
        foreach ($this->textsTheAgentReads() as $where => $text) {
            $this->assertSame([], ContentRules::forbiddenTermsIn($text), "Termo proibido em {$where}: {$text}");
        }
    }

    public function test_rn16_nenhuma_fala_marca_o_genero_do_agente_na_saudacao(): void
    {
        foreach ($this->textsTheAgentReads() as $where => $text) {
            $this->assertDoesNotMatchRegularExpression(MentoringText::GENDERED_GREETING, $text, "Saudação com gênero em {$where}");
        }
    }

    public function test_rn16_o_briefing_de_cada_missao_tambem_nao_marca_o_genero(): void
    {
        $briefings = array_filter(array_column(require database_path('data/mission_catalog.php'), 'briefing', 'slug'));

        $this->assertCount(8, $briefings);
        foreach ($briefings as $slug => $briefing) {
            $this->assertDoesNotMatchRegularExpression(MentoringText::GENDERED_GREETING, $briefing, "Saudação com gênero no briefing de {$slug}");
        }
    }

    public function test_rf06_cada_submentor_tem_pelo_menos_5_boas_vindas_diferentes(): void
    {
        foreach (Category::cases() as $area) {
            $welcomeBacks = MentoringText::submentor($area)['welcome_backs'];

            $this->assertGreaterThanOrEqual(5, count($welcomeBacks), $area->value);
            $this->assertSame($welcomeBacks, array_values(array_unique($welcomeBacks)), "{$area->value}: boas-vindas repetida");
        }
    }

    public function test_cada_texto_usa_so_os_marcadores_que_a_tela_troca(): void
    {
        foreach (Category::cases() as $area) {
            $submentor = MentoringText::submentor($area);

            foreach ([...$submentor['welcome_backs'], $submentor['closing']] as $text) {
                $this->assertMarkers([':codinome', ':missao'], $text);
            }
            foreach ($submentor['written_replies'] as $text) {
                $this->assertMarkers([':codinome'], $text);
            }
            foreach (['codename', 'animal', 'power_up', 'intro'] as $field) {
                $this->assertMarkers([], $submentor[$field]);
            }
        }

        $this->assertMarkers([':submentor'], MentoringText::content()['notice']);
    }

    public function test_rf04_o_cuidado_traz_o_cvv_na_mensagem_e_na_linha_discreta(): void
    {
        $care = MentoringText::care();

        foreach ([$care['message'], $care['footer']] as $text) {
            $this->assertStringContainsString('188', $text);
            $this->assertStringContainsString('cvv.org.br', $text);
        }
    }

    public function test_rn11_o_aviso_diz_quem_responde_e_que_o_texto_e_so_do_agente(): void
    {
        $notice = MentoringText::noticeFor(Category::Mente);

        $this->assertStringContainsString('Jabu da Silva', $notice);
        $this->assertStringContainsString('IA', $notice);
        $this->assertStringContainsString('só para você', $notice);
        $this->assertStringContainsString('apagar', $notice);
    }

    public function test_as_regras_da_ia_levam_a_lista_de_termos_de_content_rules(): void
    {
        $rules = MentoringText::aiRules();

        $this->assertStringNotContainsString(':termos', $rules);
        foreach (array_keys(ContentRules::FORBIDDEN_TERMS) as $term) {
            $this->assertStringContainsString('"'.$term.'"', $rules);
        }
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Todo texto do arquivo que o agente lê: tudo, menos a voz (só a IA lê) e as regras da IA.
     *
     * @return array<string, string>
     */
    private function textsTheAgentReads(): array
    {
        $content = MentoringText::content();
        $texts = [
            'care.message' => $content['care']['message'],
            'care.footer' => $content['care']['footer'],
            'notice' => $content['notice'],
        ];

        foreach ($content['submentors'] as $key => $submentor) {
            foreach (['codename', 'animal', 'power_up', 'intro', 'closing'] as $field) {
                $texts["{$key}.{$field}"] = $submentor[$field];
            }
            foreach (['welcome_backs', 'written_replies'] as $list) {
                foreach ($submentor[$list] as $index => $text) {
                    $texts["{$key}.{$list}.{$index}"] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertMarkers(array $allowed, string $text): void
    {
        preg_match_all('/:[a-z]+/u', $text, $found);

        $this->assertSame([], array_values(array_diff($found[0], $allowed)), "Marcador desconhecido em: {$text}");
    }
}
