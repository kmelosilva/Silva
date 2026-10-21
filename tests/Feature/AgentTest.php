<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
