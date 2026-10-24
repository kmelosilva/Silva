<?php

namespace Tests\Feature;

use App\Models\AiCall;
use App\Models\CoordinateCompletion;
use App\Models\User;
use App\Services\Mentoring\MentorModel;
use App\Support\AgentDay;
use Carbon\CarbonImmutable;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMentorModel;
use Tests\TestCase;

/**
 * A chave geral, o teto do mês e o registro do gasto (RNF-01, RNF-03, RNF-06), com um
 * modelo falso no container.
 */
class MentoringBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const MISSION = 'corpo-sao-mente-sa';

    private const FIRST = 'leia-dez-paginas';

    private const SECOND = 'pausa-de-cinco-minutos';

    private FakeMentorModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MissionCatalogSeeder::class);
        config(['mentoring.enabled' => true, 'services.anthropic.key' => 'chave-de-teste', 'mentoring.monthly_budget_usd' => 5]);
        $this->model = new FakeMentorModel;
        $this->app->instance(MentorModel::class, $this->model);
    }

    public function test_cs04_ia_desligada_da_texto_escrito_sem_chamar_nem_registrar(): void
    {
        config(['mentoring.enabled' => false]);
        $this->agentReady();

        $this->reflect(self::FIRST, 'Li dez páginas.')->assertCreated()->assertJsonPath('data.reply_source', 'written');

        $this->assertSame(0, $this->model->calls());
        $this->assertSame(0, AiCall::count());
        $this->assertNull(CoordinateCompletion::value('ai_replied_at'), 'Sem chamada, a coordenada continua podendo usar a IA.');
    }

    public function test_rnf05_ia_ligada_sem_chave_da_texto_escrito_sem_chamar(): void
    {
        config(['services.anthropic.key' => '']);
        $this->agentReady();

        $this->reflect(self::FIRST, 'Li dez páginas.')->assertCreated()->assertJsonPath('data.reply_source', 'written');

        $this->assertSame(0, $this->model->calls());
        $this->assertSame(0, AiCall::count());
    }

    public function test_rnf03_logo_abaixo_do_teto_chama_e_no_teto_para(): void
    {
        $this->agentReady();
        $this->postJson('/api/missions/'.self::MISSION.'/coordinates/'.self::SECOND.'/complete')->assertOk();
        $this->spent(4.999999, CarbonImmutable::now());

        $this->reflect(self::FIRST, 'Li dez páginas.')->assertJsonPath('data.reply_source', 'ai');
        $this->assertSame(1, $this->model->calls());

        // A chamada de agora passou o teto: a próxima já não chama.
        $this->reflect(self::SECOND, 'Fiz a pausa.')->assertCreated()->assertJsonPath('data.reply_source', 'written');
        $this->assertSame(1, $this->model->calls());
        $this->assertNull(
            CoordinateCompletion::whereHas('coordinate', fn ($query) => $query->where('slug', self::SECOND))->value('ai_replied_at'),
        );
    }

    public function test_rnf03_o_teto_recomeca_no_dia_1_de_brasilia(): void
    {
        $this->agentReady();
        $this->postJson('/api/missions/'.self::MISSION.'/coordinates/'.self::SECOND.'/complete')->assertOk();
        // 23h de 31/10 em Brasília, que já é 1/11 em UTC.
        $this->spent(5, CarbonImmutable::parse('2026-10-31 23:00', AgentDay::TIMEZONE));

        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:30', AgentDay::TIMEZONE));
        $this->reflect(self::FIRST, 'Li dez páginas.')->assertJsonPath('data.reply_source', 'written');
        $this->assertSame(0, $this->model->calls(), 'Ainda é outubro em Brasília: o gasto conta.');

        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:30', AgentDay::TIMEZONE));
        $this->reflect(self::SECOND, 'Fiz a pausa.')->assertJsonPath('data.reply_source', 'ai');
        $this->assertSame(1, $this->model->calls(), 'Novembro começou em Brasília: o teto recomeça.');
    }

    public function test_rnf02_o_custo_da_chamada_sai_dos_tokens_e_dos_precos(): void
    {
        $user = $this->agentReady();

        $this->reflect(self::FIRST, 'Li dez páginas.')->assertCreated();

        $call = AiCall::sole();
        // 1.800 tokens de entrada a US$ 0,10 e 120 de saída a US$ 0,50, por milhão.
        $this->assertSame('0.000240', $call->cost_usd);
        $this->assertSame([$user->id, 'claude-haiku-5-5', 1800, 120, 'reply', 900], [
            $call->user_id, $call->model, $call->input_tokens, $call->output_tokens, $call->outcome, $call->duration_ms,
        ]);
    }

    public function test_rnf06_o_registro_da_chamada_nunca_guarda_o_texto(): void
    {
        $this->agentReady();
        $this->reflect(self::FIRST, 'Uma frase bem particular sobre a minha casa.')->assertCreated();

        $this->assertSame(
            ['id', 'user_id', 'model', 'input_tokens', 'output_tokens', 'cost_usd', 'outcome', 'duration_ms', 'created_at'],
            Schema::getColumnListing('ai_calls')
        );
        $this->assertStringNotContainsString('particular', json_encode(AiCall::sole()->getAttributes()));
        $this->assertStringNotContainsString('Que bom', json_encode(AiCall::sole()->getAttributes()));
    }

    private function spent(float $cost, CarbonImmutable $at): void
    {
        (new AiCall)->forceFill([
            'model' => 'claude-haiku-5-5', 'input_tokens' => 0, 'output_tokens' => 0,
            'cost_usd' => $cost, 'outcome' => 'reply', 'duration_ms' => 0, 'created_at' => $at,
        ])->save();
    }

    private function agentReady(): User
    {
        $user = User::factory()->create();
        $user->ensureAgent()->update(['codename' => 'Ana da Silva']);
        Sanctum::actingAs($user);

        $this->postJson('/api/missions/'.self::MISSION.'/accept')->assertCreated();
        $this->postJson('/api/missions/'.self::MISSION.'/coordinates/'.self::FIRST.'/complete')->assertOk();

        return $user;
    }

    private function reflect(string $coordinate, string $text): TestResponse
    {
        return $this->postJson('/api/missions/'.self::MISSION."/coordinates/{$coordinate}/reflection", ['text' => $text]);
    }
}
