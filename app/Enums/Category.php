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
     * Como a categoria sai na API: {key, label}.
     *
     * @return array{key: string, label: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->value, 'label' => $this->label()];
    }
}
