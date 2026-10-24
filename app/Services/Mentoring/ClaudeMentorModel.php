<?php

namespace App\Services\Mentoring;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Client\ClientInterface;

/**
 * A resposta do submentor pelo Claude Haiku 5.5 (D-05): esforço baixo e saída em JSON com
 * dois campos, {kind: reply|care, text}. Sem temperature (400 desde a API 2026-10-21), sem
 * fallbacks (o Haiku 5.5 não aceita) e sem cache de prompt (o tráfego é espaçado).
 */
final class ClaudeMentorModel implements MentorModel
{
    /** O raciocínio conta dentro deste limite; ele também fixa o pior caso de saída. */
    public const MAX_TOKENS = 1024;

    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'kind' => ['type' => 'string', 'enum' => [MentorReply::REPLY, MentorReply::CARE]],
            'text' => ['type' => 'string'],
        ],
        'required' => ['kind', 'text'],
        'additionalProperties' => false,
    ];

    /** Código do cURL para tempo esgotado (CURLE_OPERATION_TIMEDOUT). */
    private const CURL_TIMEOUT = 28;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly float $timeoutSeconds,
    ) {}

    /**
     * O modelo com a configuração da mentoria. Conferido no SDK de PHP (v1.3.0):
     * - sem novas tentativas dentro do pedido do agente (maxRetries 0; o padrão é 2);
     * - o tempo-limite é do cliente HTTP, porque o "timeout" do SDK é só informativo;
     * - a chave vai sempre explícita, mesmo vazia: sem ela, o SDK procuraria credencial no
     *   ambiente e no perfil da máquina;
     * - sem rastreamento, para a reflexão nunca ir parar num trace.
     *
     * @param  ClientInterface|null  $transporter  o cliente HTTP; os testes passam um falso
     */
    public static function fromConfig(?ClientInterface $transporter = null): self
    {
        $timeout = (float) config('mentoring.timeout_seconds');

        $client = new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: [
                'maxRetries' => 0,
                'timeout' => $timeout,
                'transporter' => $transporter ?? new HttpClient(['timeout' => $timeout, 'connect_timeout' => min(2.0, $timeout)]),
            ],
            openTelemetry: false,
        );

        return new self($client, (string) config('mentoring.model'), $timeout);
    }

    public function reply(MentorPrompt $prompt): MentorReply
    {
        $startedAt = hrtime(true);

        try {
            $message = $this->client->messages->create(
                maxTokens: self::MAX_TOKENS,
                messages: [['role' => 'user', 'content' => $prompt->user]],
                model: $this->model,
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA],
                ],
                system: $prompt->system,
            );
        } catch (APIConnectionException $exception) {
            $durationMs = $this->elapsedMs($startedAt);

            return $this->failure($this->timedOut($exception, $durationMs) ? MentorReply::TIMEOUT : MentorReply::ERROR, $durationMs);
        } catch (APIStatusException) {
            // Resposta de erro da API (429, 5xx, 4xx), sem nova tentativa.
            return $this->failure(MentorReply::ERROR, $this->elapsedMs($startedAt));
        } catch (AnthropicException) {
            // Qualquer outra falha do SDK, como uma resposta que ele não conseguiu ler.
            return $this->failure(MentorReply::ERROR, $this->elapsedMs($startedAt));
        }

        return $this->read($message, $this->elapsedMs($startedAt));
    }

    /**
     * Confere o motivo da parada antes de ler o conteúdo; acha o primeiro bloco de texto
     * (a resposta pode começar com blocos de raciocínio); confere o esquema.
     */
    private function read(Message $message, int $durationMs): MentorReply
    {
        $result = fn (string $outcome, ?string $text = null) => new MentorReply(
            $outcome, $text, $this->model, $message->usage->inputTokens, $message->usage->outputTokens, $durationMs,
        );

        if ($message->stopReason === 'refusal') {
            return $result(MentorReply::REFUSAL);
        }
        if ($message->stopReason !== 'end_turn') {
            return $result(MentorReply::INVALID);
        }

        $text = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        $data = is_string($text) ? json_decode($text, true) : null;
        if (
            ! is_array($data)
            || count($data) !== 2
            || ! in_array($data['kind'] ?? null, [MentorReply::REPLY, MentorReply::CARE], true)
            || ! is_string($data['text'] ?? null)
        ) {
            return $result(MentorReply::INVALID);
        }

        return $result($data['kind'], $data['kind'] === MentorReply::REPLY ? trim($data['text']) : null);
    }

    private function timedOut(APIConnectionException $exception, int $durationMs): bool
    {
        $previous = $exception->getPrevious();
        if ($previous instanceof ConnectException && ($previous->getHandlerContext()['errno'] ?? null) === self::CURL_TIMEOUT) {
            return true;
        }

        return $durationMs >= $this->timeoutSeconds * 1000;
    }

    private function failure(string $outcome, int $durationMs): MentorReply
    {
        return new MentorReply($outcome, null, $this->model, 0, 0, $durationMs);
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
