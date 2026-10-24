<?php

namespace Tests\Support;

use App\Services\Mentoring\MentorModel;
use App\Services\Mentoring\MentorPrompt;
use App\Services\Mentoring\MentorReply;

/**
 * O MentorModel dos testes: devolve as respostas combinadas, em ordem, e guarda os pedidos.
 * Nenhum teste chama a rede.
 */
final class FakeMentorModel implements MentorModel
{
    /** @var list<MentorPrompt> */
    public array $prompts = [];

    /** @var list<MentorReply|\Throwable> */
    private array $replies;

    public function __construct(MentorReply|\Throwable ...$replies)
    {
        $this->replies = $replies;
    }

    public static function saying(string $text): self
    {
        return new self(self::result(MentorReply::REPLY, $text));
    }

    /**
     * Um resultado com 1.800 tokens de entrada e 120 de saída, em 900 ms.
     */
    public static function result(string $outcome, ?string $text = null): MentorReply
    {
        return new MentorReply($outcome, $text, 'claude-haiku-5-5', 1800, 120, 900);
    }

    public function reply(MentorPrompt $prompt): MentorReply
    {
        $this->prompts[] = $prompt;
        $next = array_shift($this->replies) ?? self::result(MentorReply::REPLY, 'Que bom que você contou.');

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function calls(): int
    {
        return count($this->prompts);
    }
}
