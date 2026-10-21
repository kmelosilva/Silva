<?php

namespace App\Http\Requests;

use App\Support\Codename;
use Illuminate\Foundation\Http\FormRequest;

/**
 * O nome do codinome. O "da Silva" é fixo e não vem no pedido.
 */
class CodenameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O nome chega limpo à validação: espaços repetidos e um "da Silva" digitado no fim saem.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Codename::normalizeName($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Codename::MAX_NAME_LENGTH, 'regex:'.Codename::NAME_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $onlyLetters = 'Use só letras no codinome (pode ter espaço, hífen e apóstrofo).';

        return [
            'name.required' => 'Escreva o nome do seu codinome.',
            'name.string' => $onlyLetters,
            'name.max' => 'O codinome pode ter até '.Codename::MAX_NAME_LENGTH.' letras.',
            'name.regex' => $onlyLetters,
        ];
    }
}
