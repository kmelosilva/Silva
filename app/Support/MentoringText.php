<?php

namespace App\Support;

use App\Enums\Category;

/**
 * Os textos da mentoria: os submentores do Ninho, a mensagem de cuidado, o aviso da
 * reflexão e as regras da IA. Moram em database/data/mentoring.php, para o texto existir
 * num lugar só e passar pela revisão do Kayque junto com o resto do conteúdo.
 */
final class MentoringText
{
    /**
     * O submentor da área, com codinome, bicho, power-up, apresentação, voz, fechamento,
     * boas-vindas e respostas escritas.
     *
     * @return array{codename: string, animal: string, power_up: string, intro: string, voice: string, closing: string, welcome_backs: list<string>, written_replies: list<string>}
     */
    public static function submentor(Category $area): array
    {
        return self::content()['submentors'][$area->value];
    }

    /**
     * A mensagem de cuidado (fora do personagem) e a linha discreta do CVV.
     *
     * @return array{message: string, footer: string}
     */
    public static function care(): array
    {
        return self::content()['care'];
    }

    /**
     * O aviso antes de escrever, com o codinome do submentor da área.
     */
    public static function noticeFor(Category $area): string
    {
        return strtr(self::content()['notice'], [':submentor' => self::submentor($area)['codename']]);
    }

    /**
     * As regras da Agência para a IA, com a lista de termos proibidos de ContentRules.
     */
    public static function aiRules(): string
    {
        $terms = implode(', ', array_map(
            fn (string $term) => '"'.$term.'"',
            array_keys(ContentRules::FORBIDDEN_TERMS),
        ));

        return strtr(self::content()['ai']['rules'], [':termos' => $terms]);
    }

    /**
     * O arquivo inteiro, como está.
     *
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        return require database_path('data/mentoring.php');
    }
}
