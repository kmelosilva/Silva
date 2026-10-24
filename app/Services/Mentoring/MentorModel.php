<?php

namespace App\Services\Mentoring;

/**
 * A chamada à IA que responde à reflexão. A real é o ClaudeMentorModel; os testes ligam
 * um falso no container, sem rede.
 */
interface MentorModel
{
    /**
     * Nunca lança: recusa, resposta fora do esquema, tempo esgotado e erro voltam como resultado.
     */
    public function reply(MentorPrompt $prompt): MentorReply;
}
