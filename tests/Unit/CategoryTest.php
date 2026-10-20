<?php

namespace Tests\Unit;

use App\Enums\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function test_rf03_a_lista_de_categorias_e_exatamente_a_aprovada(): void
    {
        $this->assertSame(
            ['saude', 'mente', 'financas', 'carreira', 'social', 'habitos'],
            array_column(Category::cases(), 'value')
        );
    }

    public function test_rf03_toda_categoria_tem_rotulo_em_portugues(): void
    {
        $this->assertSame(
            ['Saúde', 'Mente', 'Finanças', 'Carreira', 'Social', 'Hábitos'],
            array_map(fn (Category $category) => $category->label(), Category::cases())
        );
    }
}
