<?php

/*
 * Catálogo de missões da Silva — a fonte que a carga grava no banco
 * (App\Services\MissionCatalogSync, chamado pelo MissionCatalogSeeder).
 *
 * A estrutura vem da Grade de Conteúdos (Drive, 2023). Os nomes das missões são
 * da marca; os títulos e textos das coordenadas da Grade vieram de fora, então
 * foram escritos do zero em 2026-10-19 — versão inicial, aguardando a revisão do
 * Kayque. Nenhum texto de terceiros entra aqui.
 *
 * - "slug" é a chave da carga: mudar o slug cria outra missão e aposenta a antiga.
 * - A ordem do array é a ordem de exibição, das missões e das coordenadas.
 * - "category" usa as chaves de App\Enums\Category; a missão de entrada não tem.
 * - Coordenada com "content" null fica oculta, e missão sem coordenada com texto também.
 * - Linhas seguidas viram um parágrafo; uma linha em branco separa parágrafos.
 * - Regras de texto: saúde sem peso e gamificação que não pune (README das specs).
 */

$xp = 20; // igual para todas as coordenadas nesta versão

return [
    [
        'slug' => 'seja-um-agente',
        'title' => 'Seja um agente',
        'description' => 'A primeira missão de todo agente: entrar para a Agência e assumir o compromisso com a própria jornada.',
        'category' => null,
        'is_entry' => true,
        'coordinates' => [
            [
                'slug' => 'siga-o-codigo-de-etica',
                'title' => 'Siga o código de ética e conduta da Agência',
                'content' => null, // o código de ética ainda não existe por escrito
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'nao-espere-o-sol',
        'title' => 'Não espere o Sol',
        'description' => 'A primeira hora do dia é território livre: ninguém te liga, ninguém te cobra. Esta missão é tomar posse dela.',
        'category' => 'habitos',
        'coordinates' => [
            [
                'slug' => 'acorde-meia-hora-antes',
                'title' => 'Acorde meia hora antes, só para você',
                'content' => <<<'TXT'
                    Amanhã, programe o despertador para 30 minutos antes do seu horário de sempre.
                    Essa meia hora não é para responder mensagem nem adiantar trabalho: é sua.
                    Alongue o corpo, tome um café sem pressa, leia um pouco, olhe pela janela.
                    A ideia não é virar alguém que acorda às 5 da manhã, e sim descobrir como é
                    começar o dia no seu ritmo, antes de o mundo começar a pedir coisas. Se você
                    dormiu tarde, ajuste o horário: acordar cedo não vale uma noite mal dormida.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'corpo-sao-mente-sa',
        'title' => 'Corpo são, mente sã',
        'description' => 'Três hábitos pequenos para treinar a cabeça: ler, pausar e se conhecer. Nenhum pede mais do que alguns minutos.',
        'category' => 'mente',
        'coordinates' => [
            [
                'slug' => 'leia-dez-paginas',
                'title' => 'Leia dez páginas de um livro',
                'content' => <<<'TXT'
                    Escolha um livro que você quer ler de verdade, não um que você acha que deveria
                    ler. Vale romance, quadrinho, biografia ou livro técnico. Hoje, leia dez páginas
                    com o celular longe. Se o livro não prender, troque sem culpa: a missão é criar
                    o hábito de ler, não terminar um título específico. No final, anote numa frase
                    o que ficou na sua cabeça. Ler é ver o mundo pelos olhos de outra pessoa, e todo
                    agente precisa de mais de um ponto de vista.
                    TXT,
                'xp' => $xp,
            ],
            [
                'slug' => 'pausa-de-cinco-minutos',
                'title' => 'Faça uma pausa de cinco minutos em silêncio',
                'content' => <<<'TXT'
                    Sente num lugar confortável, coloque um alarme de cinco minutos e feche os olhos.
                    Preste atenção na respiração: o ar entrando, o ar saindo. Quando o pensamento
                    fugir para a lista de tarefas, e ele vai fugir, perceba e volte para a
                    respiração. Esse é o exercício inteiro. Não existe jeito errado de fazer, nem
                    mente que “não consegue”. Se cinco minutos parecerem muito, comece com dois. O
                    que conta é parar de propósito, por um instante, antes de seguir o dia.
                    TXT,
                'xp' => $xp,
            ],
            [
                'slug' => 'escreva-quem-voce-e-hoje',
                'title' => 'Escreva quem você é hoje',
                'content' => <<<'TXT'
                    Pegue papel ou o bloco de notas do celular e responda, sem pensar demais: do que
                    eu gosto? O que me irrita? O que eu faço bem? O que eu quero que esteja diferente
                    daqui a um ano? Escreva do jeito que vier, sem corrigir; ninguém vai ler além de
                    você. Depois, guarde a resposta. Daqui a alguns meses, ela vai mostrar o quanto
                    você mudou. Seu passado não diz tudo sobre você, e o seu presente também não:
                    ele é só o ponto de partida.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'luz-no-fim-do-tunel',
        'title' => 'Luz no fim do túnel',
        'description' => 'Todo agente tem um problema que vem adiando. Esta missão é olhar para ele de frente, um passo de cada vez.',
        'category' => 'mente',
        'coordinates' => [
            [
                'slug' => 'de-o-primeiro-passo',
                'title' => 'Dê o primeiro passo naquele problema adiado',
                'content' => <<<'TXT'
                    Pense num problema que está te incomodando há um tempo: uma conversa difícil, uma
                    conta atrasada, um projeto parado. Escreva o problema numa linha. Depois, escreva
                    o menor passo possível para começar a resolver: mandar uma mensagem, pesquisar
                    uma informação, separar 15 minutos na agenda. Faça só esse passo, hoje. Você não
                    precisa resolver tudo de uma vez, precisa sair do lugar. Problema encarado de
                    frente costuma ser menor do que parecia quando estava escondido.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'negociando-com-cronos',
        'title' => 'Negociando com Cronos',
        'description' => 'Cronos, o senhor do tempo, não dá desconto para ninguém. Mas aceita negociar com quem chega com um plano.',
        'category' => 'carreira',
        'coordinates' => [
            [
                'slug' => 'escolha-tres-prioridades',
                'title' => 'Escolha as três prioridades do dia',
                'content' => <<<'TXT'
                    Antes de começar o dia, escreva tudo o que você precisa fazer. Depois, circule só
                    três itens: os que fariam o dia valer a pena se fossem os únicos a sair. Comece
                    pelo primeiro e só passe para o próximo quando terminar. O resto da lista
                    continua existindo, mas não manda mais em você. No fim do dia, confira quantos
                    dos três saíram. O número importa menos do que o treino: decidir para onde o seu
                    tempo vai, antes que Cronos decida por você.
                    TXT,
                'xp' => $xp,
            ],
            [
                'slug' => 'transforme-um-desejo-em-meta',
                'title' => 'Transforme um desejo em meta',
                'content' => <<<'TXT'
                    Escolha algo que você quer e que ainda está vago, como “quero aprender inglês” ou
                    “quero viajar”. Reescreva até caber em três partes: o quê, quanto e até quando.
                    Por exemplo: “terminar as dez primeiras aulas do curso em 30 dias” ou “guardar
                    R$ 50 por semana até dezembro para a viagem”. Depois, escreva o primeiro passo
                    desta semana. Desejo vago não tem como ser cumprido, porque nunca fica claro
                    quando você chegou lá. Meta clara diz exatamente o que fazer amanhã.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'gerindo-recursos',
        'title' => 'Gerindo recursos',
        'description' => 'Todo agente precisa saber com que recursos conta. Esta missão é olhar para o seu dinheiro sem medo.',
        'category' => 'financas',
        'coordinates' => [
            [
                'slug' => 'mapeie-entradas-e-saidas',
                'title' => 'Mapeie o que entra e o que sai',
                'content' => <<<'TXT'
                    Separe 20 minutos e anote quanto dinheiro entra por mês e para onde ele vai:
                    aluguel, contas, transporte, comida, assinaturas, lazer. Use o extrato do banco
                    para não depender da memória. Não é hora de se julgar, só de enxergar. No final,
                    marque um gasto que você nem lembrava que tinha e decida se ele continua. Quem
                    sabe para onde o dinheiro vai consegue escolher para onde ele deveria ir. É o
                    começo de qualquer reserva, por menor que seja.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'nova-realidade',
        'title' => 'Nova realidade',
        'description' => 'A realidade é uma construção, feita de escolhas pequenas. Esta missão é fazer uma escolha diferente da de sempre.',
        'category' => 'mente',
        'coordinates' => [
            [
                'slug' => 'faca-algo-pela-primeira-vez',
                'title' => 'Faça uma coisa pela primeira vez',
                'content' => <<<'TXT'
                    Escolha algo pequeno que você nunca fez: um caminho novo para casa, uma comida
                    que nunca provou, puxar conversa com alguém diferente, uma aula experimental, um
                    passeio sem companhia. Tem que dar um frio na barriga, mas sem colocar você em
                    risco. Faça hoje ou marque o dia. Depois, repare no que sentiu antes, durante e
                    depois. Quase sempre o medo é maior na véspera do que na hora. A zona de conforto
                    não é um lugar ruim; ela só fica pequena quando a gente nunca sai dela.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
    [
        'slug' => 'celebre-a-vida',
        'title' => 'Celebre a vida',
        'description' => 'Agente que só olha para o que falta esquece o caminho que já fez. Esta missão é comemorar.',
        'category' => 'mente',
        'coordinates' => [
            [
                'slug' => 'comemore-uma-conquista',
                'title' => 'Comemore uma conquista desta semana',
                'content' => <<<'TXT'
                    Pense na última semana e escolha uma coisa que deu certo, grande ou pequena: uma
                    tarefa entregue, uma conversa difícil que você encarou, um dia em que você se
                    cuidou. Agora comemore de verdade: conte para alguém, escreva num lugar que você
                    vai rever ou se dê um momento bom, como uma música alta, um passeio, um café num
                    lugar de que você gosta. Celebrar não é se gabar. É treinar o olhar para
                    enxergar o que você já construiu, e não só o que ainda falta.
                    TXT,
                'xp' => $xp,
            ],
        ],
    ],
];
