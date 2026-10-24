<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reflexão do agente sobre a coordenada: de 1 a 500 caracteres, depois de tirar os
 * espaços das pontas (o Laravel já tira, e texto só de espaços vira vazio).
 */
class ReflectionRequest extends FormRequest
{
    public const MAX_LENGTH = 500;

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
            'text' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Escreva o que você viveu antes de enviar.',
            'text.string' => 'Escreva o que você viveu antes de enviar.',
            'text.max' => 'Escreva até '.self::MAX_LENGTH.' caracteres.',
        ];
    }

    public function text(): string
    {
        return $this->validated('text');
    }
}
