<?php

/*
 * Textos do alistamento — a fonte que a API mostra (App\Support\EnlistmentText) e que o
 * catálogo usa na coordenada "Siga o código de ética e conduta" (mission_catalog.php).
 *
 * - "oath" é a Ficha de alistamento da Silva (Drive, 2023), palavra por palavra, com :nome
 *   no lugar do nome da conta. Só ganhou a vírgula depois do nome.
 * - "code_of_conduct" não existia por escrito. Versão inicial de 2026-10-20, escrita a partir
 *   de textos da marca no Drive (Descrição das missões, Sobre Nós, a Ficha e a Alma da sua
 *   marca), aguardando a revisão do Kayque.
 * - Regras de texto: saúde sem peso e gamificação que não pune (README das specs).
 */

return [
    'oath' => 'Eu, :nome, aceito seguir em desenvolvimento pelo meu bem e pelo bem de todos perante o código de ética e conduta da Silva, e assim, cumprir as missões da minha jornada.',

    'code_of_conduct' => [
        [
            'title' => 'Você conduz a sua jornada.',
            'text' => 'Ninguém cumpre uma missão por você, e ninguém conhece a sua vida como você. O primeiro passo de qualquer mudança é seu.',
        ],
        [
            'title' => 'Errar faz parte da missão.',
            'text' => 'Ser agente não é nunca errar: é tentar ser um pouco melhor a cada dia. A coordenada que não deu hoje pode ser cumprida amanhã.',
        ],
        [
            'title' => 'Pelo seu bem e pelo bem de todos.',
            'text' => 'Nenhuma conquista vale o mal de outra pessoa. O que você faz por você não passa por cima de ninguém.',
        ],
        [
            'title' => 'Todo mundo é Silva.',
            'text' => 'Gente de todo jeito, de toda realidade e de todo tamanho cabe na Agência. Aqui ninguém é julgado pelo corpo, pelo bolso ou pelo passado.',
        ],
        [
            'title' => 'Cada um no seu ritmo.',
            'text' => 'A jornada não é uma corrida. Compare-se com quem você era ontem, não com a vida dos outros.',
        ],
        [
            'title' => 'Corpo e mente são equipamento de agente.',
            'text' => 'Energia, movimento, sono e uma boa alimentação deixam você pronto para as missões. Cuidar de si não é luxo: é parte do trabalho.',
        ],
        [
            'title' => 'Celebre e compartilhe.',
            'text' => 'Comemore cada conquista, grande ou pequena, e as dos outros agentes também. O que você aprende numa missão pode ajudar alguém na dele.',
        ],
    ],
];
