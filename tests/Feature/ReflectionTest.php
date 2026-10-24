<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Models\Agent;
use App\Models\Reflection;
use App\Models\User;
use App\Support\MentoringText;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReflectionTest extends TestCase
{
    use RefreshDatabase;

    // "Mente sã", da área Mente: o submentor é o Jabu da Silva.
    private const MISSION = 'corpo-sao-mente-sa';

    private const FIRST = 'leia-dez-paginas';

    private const SECOND = 'pausa-de-cinco-minutos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
    }

    public function test_sem_login_responde_401(): void
    {
        $this->reflect(self::MISSION, self::FIRST, 'Li dez páginas.')->assertUnauthorized();
    }

    public function test_rf03_sem_cumprir_a_coordenada_e_recusado(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);

        $this->reflect(self::MISSION, self::FIRST, 'Ainda não li.')
            ->assertStatus(409)
            ->assertExactJson(['error' => [
                'code' => 'reflection_requires_completion',
                'message' => 'Cumpra a coordenada antes de escrever sobre ela.',
            ]]);
        $this->assertSame(0, Reflection::count());
    }

    public function test_rn02_missao_de_entrada_e_missao_fora_dos_modulos_dao_404(): void
    {
        $this->signIn();
        $this->postJson('/api/enlistment/oath');
        $this->postJson('/api/enlistment/codename', ['name' => 'Ana']);
        $this->postJson('/api/enlistment/complete', ['priorities' => []]);
        $this->postJson('/api/missions/seja-um-agente/coordinates/siga-o-codigo-de-etica/complete')->assertOk();

        $this->reflect('seja-um-agente', 'siga-o-codigo-de-etica', 'Escolhi um item.')->assertNotFound();
        $this->reflect('nao-existe', self::FIRST, 'Oi.')->assertNotFound();
        $this->reflect(self::MISSION, 'nao-existe', 'Oi.')->assertNotFound();
    }

    public function test_rf03_texto_vazio_ou_longo_demais_e_recusado(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $this->reflect(self::MISSION, self::FIRST, '   ')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['text' => 'Escreva o que você viveu antes de enviar.']);
        $this->reflect(self::MISSION, self::FIRST, str_repeat('a', 501))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['text' => 'Escreva até 500 caracteres.']);
        $this->reflect(self::MISSION, self::FIRST, '  '.str_repeat('a', 500).'  ')->assertCreated();
    }

    public function test_rf03_com_a_ia_desligada_a_resposta_e_o_texto_escrito_do_submentor(): void
    {
        $this->signIn('Ana da Silva');
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $reflection = $this->reflect(self::MISSION, self::FIRST, '  Li dez páginas e gostei.  ')
            ->assertCreated()
            ->assertJsonPath('data.mission_slug', self::MISSION)
            ->assertJsonPath('data.mission_title', 'Mente sã')
            ->assertJsonPath('data.coordinate_slug', self::FIRST)
            ->assertJsonPath('data.text', 'Li dez páginas e gostei.')
            ->assertJsonPath('data.reply_source', 'written')
            ->assertJsonPath('data.submentor', ['area' => 'mente', 'codename' => 'Jabu da Silva'])
            ->json('data');

        $written = array_map(
            fn (string $reply) => strtr($reply, [':codinome' => 'Ana da Silva']),
            MentoringText::submentor(Category::Mente)['written_replies'],
        );
        $this->assertContains($reflection['reply'], $written);
    }

    public function test_rf03_escrever_de_novo_devolve_a_mesma_reflexao_com_200(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $first = $this->reflect(self::MISSION, self::FIRST, 'Primeira vez.')->assertCreated()->json('data');
        $second = $this->reflect(self::MISSION, self::FIRST, 'Segunda vez.')->assertOk()->json('data');

        $this->assertSame($first, $second);
        $this->assertSame(1, Reflection::count(), 'Exatamente uma reflexão, nem zero nem duas.');
    }

    public function test_rf04_sinal_de_risco_responde_com_o_cuidado(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $this->reflect(self::MISSION, self::FIRST, 'Li, mas hoje não quero mais viver.')
            ->assertCreated()
            ->assertJsonPath('data.reply_source', 'care')
            ->assertJsonPath('data.reply', MentoringText::care()['message']);
    }

    public function test_rn07_escrever_nao_mexe_no_xp_no_nivel_nem_na_sequencia(): void
    {
        $user = $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);
        $before = $this->agentState($user);

        $this->reflect(self::MISSION, self::FIRST, 'Li dez páginas.')->assertCreated();

        $this->assertSame($before, $this->agentState($user));
        $this->getJson('/api/missions/my')->assertJsonPath('data.0.next_coordinate', self::SECOND);
    }

    public function test_um_agente_nao_escreve_sobre_a_coordenada_cumprida_por_outro(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $this->signIn();
        $this->accept(self::MISSION);

        $this->reflect(self::MISSION, self::FIRST, 'Não cumpri.')->assertStatus(409);
    }

    public function test_mais_de_10_envios_por_minuto_dao_429(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        for ($i = 1; $i <= 10; $i++) {
            $this->reflect(self::MISSION, self::FIRST, "Envio {$i}.")->assertSuccessful();
        }

        $this->reflect(self::MISSION, self::FIRST, 'Envio 11.')->assertTooManyRequests();
    }

    public function test_contrato_da_reflexao_tem_exatamente_os_campos_combinados(): void
    {
        $this->signIn();
        $this->accept(self::MISSION);
        $this->complete(self::MISSION, self::FIRST);

        $reflection = $this->reflect(self::MISSION, self::FIRST, 'Li dez páginas.')->json('data');

        $this->assertSame(
            ['id', 'mission_slug', 'mission_title', 'coordinate_slug', 'coordinate_title', 'text', 'reply', 'reply_source', 'submentor', 'created_at'],
            array_keys($reflection)
        );
    }

    private function signIn(?string $codename = null): User
    {
        $user = User::factory()->create();
        if ($codename) {
            $user->ensureAgent()->update(['codename' => $codename]);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function agentState(User $user): array
    {
        return Agent::where('user_id', $user->id)->firstOrFail()
            ->only(['xp', 'level', 'streak_current', 'streak_best', 'last_active_on']);
    }

    private function accept(string $mission): TestResponse
    {
        return $this->postJson("/api/missions/{$mission}/accept");
    }

    private function complete(string $mission, string $coordinate): TestResponse
    {
        return $this->postJson("/api/missions/{$mission}/coordinates/{$coordinate}/complete");
    }

    private function reflect(string $mission, string $coordinate, string $text): TestResponse
    {
        return $this->postJson("/api/missions/{$mission}/coordinates/{$coordinate}/reflection", ['text' => $text]);
    }
}
