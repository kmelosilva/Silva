<?php

namespace App\Enums;

/**
 * A única lista de categorias do Silva. A API manda a chave e o rótulo;
 * o front só guarda a parte visual de cada chave.
 */
enum Category: string
{
    case Saude = 'saude';
    case Mente = 'mente';
    case Financas = 'financas';
    case Carreira = 'carreira';
    case Social = 'social';
    case Habitos = 'habitos';

    public function label(): string
    {
        return match ($this) {
            self::Saude => 'Saúde',
            self::Mente => 'Mente',
            self::Financas => 'Finanças',
            self::Carreira => 'Carreira',
            self::Social => 'Social',
            self::Habitos => 'Hábitos',
        };
    }

    /**
     * A explicação curta que aparece na escolha de prioridade do alistamento.
     */
    public function description(): string
    {
        return match ($this) {
            self::Saude => 'Energia, movimento, sono e alimentação.',
            self::Mente => 'Leitura, pausa e autoconhecimento.',
            self::Financas => 'Dinheiro organizado, sem aperto e sem culpa.',
            self::Carreira => 'Trabalho, estudo e metas para o futuro.',
            self::Social => 'Amizades, família e as pessoas ao seu redor.',
            self::Habitos => 'Rotina, tempo e as pequenas escolhas de cada dia.',
        };
    }

    /**
     * Como a categoria sai na API: {key, label}.
     *
     * @return array{key: string, label: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->value, 'label' => $this->label()];
    }
}
