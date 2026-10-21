<?php

namespace Tests\Unit;

use App\Support\Codename;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CodenameTest extends TestCase
{
    #[DataProvider('accountNames')]
    public function test_rf03_sugere_a_primeira_palavra_do_nome_da_conta(string $accountName, ?string $suggested): void
    {
        $this->assertSame($suggested, Codename::suggestFor($accountName));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function accountNames(): array
    {
        return [
            'nome e sobrenome' => ['Kayque Melo', 'Kayque'],
            'tudo minúsculo' => ['kayque melo', 'Kayque'],
            'com acento' => ['élida souza', 'Élida'],
            'espaços em volta' => ['  Ana  ', 'Ana'],
            'apóstrofo e hífen ficam' => ["D'Ávila Ana-Clara", "D'Ávila"],
            'número na frente é ignorado' => ['123 Bia', 'Bia'],
            'símbolo no meio sai' => ['Jo@ão', 'João'],
            'sem letras' => ['1234', null],
            'vazio' => ['', null],
        ];
    }

    public function test_rn04_codinome_e_o_nome_seguido_de_da_silva(): void
    {
        $this->assertSame('Kayque da Silva', Codename::build('Kayque'));
        $this->assertSame('Ana Clara da Silva', Codename::build('Ana   Clara'));
    }

    #[DataProvider('typedSuffix')]
    public function test_rn04_da_silva_digitado_no_fim_nao_dobra(string $typed): void
    {
        $this->assertSame('Kay da Silva', Codename::build($typed));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function typedSuffix(): array
    {
        return [
            'igual ao sufixo' => ['Kay da Silva'],
            'minúsculo' => ['Kay da silva'],
            'espaços a mais' => ['  Kay   da   Silva  '],
        ];
    }

    public function test_rn04_silva_sem_o_da_nao_e_cortado(): void
    {
        $this->assertSame('Kayque Silva da Silva', Codename::build('Kayque Silva'));
    }

    public function test_rf03_regra_do_nome_aceita_letras_e_separadores(): void
    {
        foreach (['Kayque', 'Élida', "D'Ávila", 'Ana-Clara', 'Ana Clara', 'João’s'] as $valid) {
            $this->assertSame(1, preg_match(Codename::NAME_PATTERN, $valid), "Deveria aceitar \"{$valid}\".");
        }

        foreach (['Kay1', 'Ana!', ' Ana', 'Ana ', 'Ana  Clara', '-Ana', ''] as $invalid) {
            $this->assertSame(0, preg_match(Codename::NAME_PATTERN, $invalid), "Deveria recusar \"{$invalid}\".");
        }
    }

    public function test_sugestao_longa_e_cortada_no_limite_do_nome(): void
    {
        $this->assertSame(Codename::MAX_NAME_LENGTH, mb_strlen(Codename::suggestFor(str_repeat('a', 40))));
    }
}
