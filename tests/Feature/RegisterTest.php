<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'segredo123',
            'adult' => true,
            ...$overrides,
        ];
    }

    public function test_rf01_o_token_do_cadastro_ja_abre_o_app(): void
    {
        $token = $this->postJson('/api/register', $this->form())->assertCreated()->json('data.token');

        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/agent')
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana Souza');
    }

    #[DataProvider('withoutDeclaration')]
    public function test_rf01_sem_a_declaracao_de_18_anos_a_conta_nao_e_criada(array $overrides): void
    {
        $this->postJson('/api/register', $this->form($overrides))
            ->assertUnprocessable()
            ->assertJsonPath('errors.adult.0', 'Nesta versão, a Silva é só para quem tem 18 anos ou mais.');

        $this->assertSame(0, User::count());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function withoutDeclaration(): array
    {
        return [
            'faltando' => [['adult' => null]],
            'falso' => [['adult' => false]],
        ];
    }

    public function test_rnf03_a_declaracao_nao_e_gravada(): void
    {
        $this->postJson('/api/register', $this->form())->assertCreated();

        $this->assertArrayNotHasKey('adult', User::firstOrFail()->getAttributes());
    }

    #[DataProvider('refusals')]
    public function test_rf01_recusa_do_cadastro_vem_em_portugues(array $overrides, string $field, string $message): void
    {
        User::factory()->create(['email' => 'usada@example.com']);

        $this->postJson('/api/register', $this->form($overrides))
            ->assertUnprocessable()
            ->assertJsonPath("errors.{$field}.0", $message);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function refusals(): array
    {
        return [
            'e-mail já usado' => [['email' => 'usada@example.com'], 'email', 'Já existe uma conta com esse e-mail. Entre com ele.'],
            'e-mail inválido' => [['email' => 'ana'], 'email', 'Esse e-mail não parece válido.'],
            'senha curta' => [['password' => '123'], 'password', 'A senha precisa de pelo menos 6 caracteres.'],
            'sem nome' => [['name' => ''], 'name', 'Diga como você se chama.'],
        ];
    }
}
