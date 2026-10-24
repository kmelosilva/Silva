<?php

namespace App\Services\Mentoring;

use App\Models\Coordinate;
use App\Models\Mission;
use App\Support\MentoringText;

/**
 * O pedido à IA, numa volta só (D-06). No sistema: as regras da Agência, a voz do submentor
 * da área e o contexto da coordenada. No usuário: o codinome e a reflexão, marcada como
 * texto do agente, não instrução. Nada mais sai do Silva: nem e-mail, nem nome da conta,
 * nem outras reflexões.
 */
final class MentorPrompt
{
    /** O texto da coordenada vai cortado: é contexto, não precisa ir inteiro. */
    public const COORDINATE_MAX_CHARS = 600;

    public function __construct(
        public readonly string $system,
        public readonly string $user,
    ) {}

    public static function for(Mission $mission, Coordinate $coordinate, string $codename, string $reflection): self
    {
        $submentor = MentoringText::submentor($mission->category);
        $content = preg_replace('/\s+/u', ' ', (string) $coordinate->content);

        $system = implode("\n\n", [
            MentoringText::aiRules(),
            "Quem responde: {$submentor['codename']}.\n{$submentor['voice']}",
            "A coordenada que o agente cumpriu, na missão \"{$mission->title}\": \"{$coordinate->title}\".\n"
                .'Texto da coordenada: '.mb_substr($content, 0, self::COORDINATE_MAX_CHARS),
        ]);

        // As marcas são tiradas do texto do agente, para ele não fechar a reflexão por conta própria.
        $text = str_ireplace(['<reflexao>', '</reflexao>'], '', $reflection);

        return new self($system, "Codinome do agente: {$codename}\n<reflexao>\n{$text}\n</reflexao>");
    }
}
