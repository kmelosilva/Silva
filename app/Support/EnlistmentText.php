<?php

namespace App\Support;

/**
 * Os textos do alistamento: o juramento da Ficha e o código de ética e conduta.
 * Moram em database/data/enlistment.php, o mesmo arquivo que o catálogo usa na
 * coordenada do código, para o texto existir num lugar só.
 */
final class EnlistmentText
{
    /**
     * O juramento, com o nome da conta no lugar de :nome.
     */
    public static function oathFor(string $name): string
    {
        return strtr(self::content()['oath'], [':nome' => trim($name)]);
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public static function codeOfConduct(): array
    {
        return self::content()['code_of_conduct'];
    }

    /**
     * @return array{oath: string, code_of_conduct: list<array{title: string, text: string}>}
     */
    private static function content(): array
    {
        return require database_path('data/enlistment.php');
    }
}
