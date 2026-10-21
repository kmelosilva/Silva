<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Uma regra da agência recusou o pedido. Vira a própria resposta:
 * { "error": { "code": ..., "message": ... } }, com a mensagem pronta para a tela.
 * Cada área tem a sua subclasse, com as recusas dela.
 */
abstract class RequestRefused extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $this->errorCode, 'message' => $this->getMessage(), ...$this->details],
        ], $this->status);
    }
}
