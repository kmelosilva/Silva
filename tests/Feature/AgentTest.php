<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rf01_cadastro_cria_o_agente_com_nivel_1_e_0_xp(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'segredo123',
        ])->assertCreated();

        $agent = User::where('email', 'ana@example.com')->firstOrFail()->agent;

        $this->assertNotNull($agent);
        $this->assertSame(1, $agent->level);
        $this->assertSame(0, $agent->xp);
    }

    public function test_rf01_toda_conta_criada_por_qualquer_caminho_tem_agente(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(3, Agent::count());
        $this->assertSame(0, User::doesntHave('agent')->count());
    }

    public function test_rf01_conta_sem_agente_ganha_um_e_so_um(): void
    {
        $user = User::factory()->create();
        $user->agent->delete();
        $user->unsetRelation('agent');

        $agent = $user->ensureAgent();
        $again = $user->fresh()->ensureAgent();

        $this->assertTrue($agent->is($again));
        $this->assertSame(1, $agent->level);
        $this->assertSame(1, Agent::where('user_id', $user->id)->count());
    }

    public function test_rf01_o_banco_recusa_dois_agentes_para_a_mesma_conta(): void
    {
        $user = User::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Agent::create(['user_id' => $user->id]);
    }

    public function test_rf01_conta_sem_agente_ganha_um_no_primeiro_acesso(): void
    {
        $user = User::factory()->create();
        $user->agent->delete();
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/agent')
            ->assertOk()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.xp', 0);

        $this->assertSame(1, Agent::where('user_id', $user->id)->count());
    }

    public function test_rf02_cada_conta_ve_os_proprios_numeros(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $ana->agent->update(['level' => 3, 'xp' => 200]);
        $bia = User::factory()->create(['name' => 'Bia']);

        Sanctum::actingAs($ana);
        $this->getJson('/api/agent')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana')
            ->assertJsonPath('data.level', 3)
            ->assertJsonPath('data.xp', 200)
            ->assertJsonPath('data.level_xp', 150)
            ->assertJsonPath('data.next_level_xp', 300);

        Sanctum::actingAs($bia);
        $this->getJson('/api/agent')
            ->assertOk()
            ->assertJsonPath('data.name', 'Bia')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.xp', 0)
            ->assertJsonPath('data.level_xp', 0)
            ->assertJsonPath('data.next_level_xp', 50)
            ->assertJsonPath('data.missions_completed', 0)
            ->assertJsonPath('data.streak', ['current' => 0, 'best' => 0]);
    }

    public function test_rf02_o_painel_nunca_chama_o_agente_pelo_nome_do_mentor(): void
    {
        Sanctum::actingAs(User::factory()->create(['name' => 'Kayque']));

        $panel = $this->getJson('/api/agent')->assertOk()->json('data');

        $this->assertSame('Kayque', $panel['name']);
        $this->assertStringNotContainsString('Agente Silva', json_encode($panel));
    }

    public function test_contrato_do_painel_tem_exatamente_os_campos_combinados(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $panel = $this->getJson('/api/agent')->assertOk()->json('data');

        $this->assertSame(
            ['name', 'level', 'xp', 'level_xp', 'next_level_xp', 'missions_completed', 'streak'],
            array_keys($panel)
        );
        $this->assertSame(['current', 'best'], array_keys($panel['streak']));
    }

    #[DataProvider('cycleRoutes')]
    public function test_rnf02_rotas_do_ciclo_pedem_login(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cycleRoutes(): array
    {
        return [
            'painel' => ['GET', '/api/agent'],
            'missões aceitas' => ['GET', '/api/missions/my'],
            'aceitar' => ['POST', '/api/missions/corpo-sao-mente-sa/accept'],
            'cumprir' => ['POST', '/api/missions/corpo-sao-mente-sa/coordinates/leia-dez-paginas/complete'],
        ];
    }
}
