<?php

namespace Tests\Feature;

use App\Enums\Category;
use App\Support\ContentRules;
use App\Support\MentoringText;
use Tests\TestCase;

class SubmentorTest extends TestCase
{
    /** Saudação que marca o gênero do agente (RN-16). */
    private const GENDERED_GREETING = '/\bbem[- ]?vind[oa]s?\b/iu';

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
            $this->assertDoesNotMatchRegularExpression(self::GENDERED_GREETING, $text, "Saudação com gênero em {$where}");
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
