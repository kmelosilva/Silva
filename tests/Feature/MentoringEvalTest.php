<?php

namespace Tests\Feature;

use App\Models\AiCall;
use App\Models\Reflection;
use App\Services\Mentoring\MentorModel;
use App\Services\Mentoring\MentorPrompt;
use App\Services\Mentoring\MentorReply;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeMentorModel;
use Tests\TestCase;

/**
 * O comando de avaliação, com um modelo falso: confere o relatório sem gastar nada.
 * A rodada de verdade, com a API, é a do T-21.
 */
class MentoringEvalTest extends TestCase
{
    use RefreshDatabase;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = sys_get_temp_dir().'/mentoring-eval-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);

        parent::tearDown();
    }

    public function test_sem_chave_o_comando_para_sem_chamar_nada(): void
    {
        config(['services.anthropic.key' => '']);
        $model = new FakeMentorModel;
        $this->app->instance(MentorModel::class, $model);

        $this->artisan('mentoring:eval', ['--output' => $this->output])
            ->expectsOutputToContain('Sem ANTHROPIC_API_KEY')
            ->assertFailed();

        $this->assertSame(0, $model->calls());
        $this->assertDirectoryDoesNotExist($this->output);
    }

    public function test_sem_o_catalogo_o_comando_diz_o_que_falta(): void
    {
        config(['services.anthropic.key' => 'chave-de-teste']);

        $this->artisan('mentoring:eval', ['--output' => $this->output])
            ->expectsOutputToContain('Rode a carga do catálogo antes')
            ->assertFailed();
    }

    public function test_d11_a_rodada_passa_as_36_reflexoes_pelas_barreiras_e_escreve_o_relatorio(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        config(['services.anthropic.key' => 'chave-de-teste', 'mentoring.enabled' => false]);
        // Um modelo que reconhece as duas crises que a lista não pega e responde o resto.
        $model = new class implements MentorModel
        {
            public int $calls = 0;

            public function reply(MentorPrompt $prompt): MentorReply
            {
                $this->calls++;
                $crisis = str_contains($prompt->user, 'se eu não estivesse aqui') || str_contains($prompt->user, 'motivo nenhum');

                return $crisis
                    ? FakeMentorModel::result(MentorReply::CARE)
                    : FakeMentorModel::result(MentorReply::REPLY, 'Que bom que você contou, Agente de Teste da Silva.');
            }
        };
        $this->app->instance(MentorModel::class, $model);

        $this->artisan('mentoring:eval', ['--output' => $this->output])->assertSuccessful();

        $report = File::get(File::files($this->output)[0]->getPathname());
        $this->assertSame(36, substr_count($report, "\n### "));
        $this->assertStringContainsString('| Crises com a mensagem de cuidado | **6 de 6** (4 pela lista, sem chamar a IA) |', $report);
        $this->assertStringContainsString('### crise-5 — ok', $report);
        $this->assertStringNotContainsString('ATENÇÃO', $report);
        $this->assertSame(32, $model->calls, 'As 4 crises que a lista pega não chamam a IA.');
        $this->assertSame(32, AiCall::whereNull('user_id')->count(), 'O gasto da rodada entra no teto, sem agente.');
        $this->assertSame(0, Reflection::count(), 'A avaliação não grava reflexão.');
        $this->assertFalse(config('mentoring.enabled'), 'A chave geral volta como estava.');
    }
}
