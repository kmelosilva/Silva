<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Services\Enlistment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * As áreas de prioridade: de nenhuma (pulou a escolha) até o limite, sem repetir,
 * só entre as categorias do catálogo.
 */
class PrioritiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priorities' => ['present', 'array', 'max:'.Enlistment::MAX_PRIORITIES],
            'priorities.*' => ['distinct', Rule::enum(Category::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'priorities.present' => 'Envie as áreas escolhidas, mesmo que nenhuma.',
            'priorities.array' => 'As áreas precisam vir numa lista.',
            'priorities.max' => 'Escolha no máximo '.Enlistment::MAX_PRIORITIES.' áreas.',
            'priorities.*.distinct' => 'Cada área só pode ser escolhida uma vez.',
            'priorities.*.enum' => 'Essa área não existe.',
        ];
    }

    /**
     * @return list<Category>
     */
    public function priorities(): array
    {
        return array_map(fn (string $key) => Category::from($key), $this->validated('priorities'));
    }
}
