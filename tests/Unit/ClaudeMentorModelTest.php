<?php

namespace Tests\Unit;

use App\Services\Mentoring\ClaudeMentorModel;
use App\Services\Mentoring\MentorModel;
use App\Services\Mentoring\MentorPrompt;
use App\Services\Mentoring\MentorReply;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * O ClaudeMentorModel com um cliente HTTP falso: confere o pedido que sai e a leitura de
 * cada resposta, sem rede. As respostas seguem o formato da API de mensagens.
 */
class ClaudeMentorModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'chave-de-teste']);
    }

    public function test_d05_o_pedido_usa_o_haiku_com_esforco_baixo_e_o_esquema_sem_temperature(): void
    {
        $transport = $this->transport($this->message([$this->text('{"kind":"reply","text":"Que bom."}')]));

        ClaudeMentorModel::fromConfig($transport)->reply(new MentorPrompt('as regras', 'a reflexão'));

        $this->assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('/v1/messages', $request->getUri()->getPath());
        $this->assertSame('Bearer chave-de-teste', $request->getHeaderLine('Authorization'));
        $this->assertSame('2026-10-21', $request->getHeaderLine('anthropic-version'));
        $this->assertSame('claude-haiku-5-5', $body['model']);
        $this->assertSame(1024, $body['max_tokens']);
        $this->assertSame('as regras', $body['system']);
        $this->assertSame([['role' => 'user', 'content' => 'a reflexão']], $body['messages']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame(['type' => 'json_schema', 'schema' => ClaudeMentorModel::SCHEMA], $body['output_config']['format']);
        foreach (['temperature', 'top_p', 'top_k', 'fallbacks', 'thinking', 'cache_control'] as $absent) {
            $this->assertArrayNotHasKey($absent, $body, "O pedido não manda {$absent}.");
        }
    }

    public function test_d05_resposta_com_raciocinio_antes_do_texto_e_lida_pelo_primeiro_bloco_de_texto(): void
    {
        $reply = $this->replyTo($this->message([
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'assinatura'],
            $this->text('{"kind":"reply","text":"  Que bom que você leu, Ana da Silva.  "}'),
        ]));

        $this->assertSame(MentorReply::REPLY, $reply->outcome);
        $this->assertSame('Que bom que você leu, Ana da Silva.', $reply->text);
        $this->assertSame('claude-haiku-5-5', $reply->model);
        $this->assertSame(1800, $reply->inputTokens);
        $this->assertSame(120, $reply->outputTokens);
    }

    public function test_rf04_o_modelo_pode_pedir_cuidado(): void
    {
        $reply = $this->replyTo($this->message([$this->text('{"kind":"care","text":""}')]));

        $this->assertSame(MentorReply::CARE, $reply->outcome);
        $this->assertNull($reply->text);
    }

    public function test_rf03_7_recusa_do_modelo_e_registrada_com_os_tokens(): void
    {
        $reply = $this->replyTo($this->message([], 'refusal', [
            'type' => 'refusal', 'category' => 'general_harms', 'explanation' => 'Recusado.',
        ]));

        $this->assertSame(MentorReply::REFUSAL, $reply->outcome);
        $this->assertNull($reply->text);
        $this->assertSame(1800, $reply->inputTokens);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidMessages(): array
    {
        return [
            'cortada em max_tokens' => [['content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'a']], 'stop_reason' => 'max_tokens']],
            'sem bloco de texto' => [['content' => [], 'stop_reason' => 'end_turn']],
            'texto que não é JSON' => [['content' => [['type' => 'text', 'text' => 'Que bom.']], 'stop_reason' => 'end_turn']],
            'campo a mais' => [['content' => [['type' => 'text', 'text' => '{"kind":"reply","text":"Oi.","extra":1}']], 'stop_reason' => 'end_turn']],
            'kind fora da lista' => [['content' => [['type' => 'text', 'text' => '{"kind":"conselho","text":"Oi."}']], 'stop_reason' => 'end_turn']],
            'text que não é texto' => [['content' => [['type' => 'text', 'text' => '{"kind":"reply","text":3}']], 'stop_reason' => 'end_turn']],
        ];
    }

    /**
     * @param  array{content: list<array<string, mixed>>, stop_reason: string}  $message
     */
    #[DataProvider('invalidMessages')]
    public function test_rf03_7_resposta_fora_do_esquema_e_invalida(array $message): void
    {
        $reply = $this->replyTo($this->message($message['content'], $message['stop_reason']));

        $this->assertSame(MentorReply::INVALID, $reply->outcome);
        $this->assertNull($reply->text);
    }

    public function test_d05_erro_da_api_nao_tem_nova_tentativa(): void
    {
        foreach ([429, 500, 529] as $status) {
            $transport = $this->transport(
                new Response($status, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'x']])),
                $this->message([$this->text('{"kind":"reply","text":"Segunda chance."}')]),
            );

            $reply = ClaudeMentorModel::fromConfig($transport)->reply(new MentorPrompt('regras', 'reflexão'));

            $this->assertSame(MentorReply::ERROR, $reply->outcome, "HTTP {$status}");
            $this->assertCount(1, $transport->requests, "HTTP {$status}: uma chamada só, sem nova tentativa.");
            $this->assertSame(0, $reply->inputTokens);
        }
    }

    public function test_d05_tempo_esgotado_e_erro_de_rede_viram_resultado_sem_nova_tentativa(): void
    {
        $request = new \GuzzleHttp\Psr7\Request('POST', 'https://api.anthropic.com/v1/messages');

        $timeout = $this->transport(new ConnectException('cURL error 28: Operation timed out', $request, null, ['errno' => 28]));
        $reply = ClaudeMentorModel::fromConfig($timeout)->reply(new MentorPrompt('regras', 'reflexão'));
        $this->assertSame(MentorReply::TIMEOUT, $reply->outcome);
        $this->assertCount(1, $timeout->requests);

        $refused = $this->transport(new ConnectException('cURL error 7: Failed to connect', $request, null, ['errno' => 7]));
        $reply = ClaudeMentorModel::fromConfig($refused)->reply(new MentorPrompt('regras', 'reflexão'));
        $this->assertSame(MentorReply::ERROR, $reply->outcome);
        $this->assertCount(1, $refused->requests);
    }

    public function test_d05_o_container_liga_o_modelo_real(): void
    {
        $this->assertInstanceOf(ClaudeMentorModel::class, app(MentorModel::class));
    }

    private function replyTo(ResponseInterface $response): MentorReply
    {
        return ClaudeMentorModel::fromConfig($this->transport($response))->reply(new MentorPrompt('regras', 'reflexão'));
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @param  array<string, string>|null  $stopDetails
     */
    private function message(array $content, string $stopReason = 'end_turn', ?array $stopDetails = null): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_teste',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'stop_details' => $stopDetails,
            'usage' => ['input_tokens' => 1800, 'output_tokens' => 120],
        ]));
    }

    /**
     * @return array{type: string, text: string}
     */
    private function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * Um cliente HTTP que devolve (ou lança) o que recebeu, em ordem, e guarda os pedidos.
     */
    private function transport(ResponseInterface|\Throwable ...$results): ClientInterface
    {
        return new class($results) implements ClientInterface
        {
            /** @var list<RequestInterface> */
            public array $requests = [];

            /**
             * @param  list<ResponseInterface|\Throwable>  $results
             */
            public function __construct(private array $results) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                $result = array_shift($this->results) ?? throw new \LogicException('Pedido a mais.');

                if ($result instanceof \Throwable) {
                    throw $result;
                }

                return $result;
            }
        };
    }
}
