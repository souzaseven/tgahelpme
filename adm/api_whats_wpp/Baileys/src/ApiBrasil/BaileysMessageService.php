<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys;

use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Dto\SendTextRequest;
use ApiBrasil\Baileys\Dto\SendTextResponse;
use ApiBrasil\Baileys\Exception\ApiBrasilException;
use ApiBrasil\Baileys\Http\CurlHttpClient;
use ApiBrasil\Baileys\Http\HttpClientInterface;
use JsonException;
use RuntimeException;

/**
 * Serviço dedicado à integração com o endpoint "WhatsApp - Baileys"
 * (Evolution API) da APIBrasil — POST /api/v2/evolution/message/sendText.
 *
 * Configuração e cliente HTTP são injetados via construtor, o que permite
 * testar a classe com dublês (mocks) e trocar a implementação HTTP sem
 * tocar na regra de negócio.
 */
final class BaileysMessageService
{
    private const SEND_TEXT_PATH = '/api/v2/evolution/message/sendText';

    public function __construct(
        private readonly ApiBrasilConfig $config,
        private readonly HttpClientInterface $httpClient = new CurlHttpClient(),
    ) {
    }

    /**
     * Envia uma mensagem de texto via WhatsApp (Baileys) através da APIBrasil.
     *
     * @throws ApiBrasilException quando a API responde com status 4xx/5xx.
     * @throws RuntimeException   em falhas de comunicação (timeout, DNS, TLS, etc.)
     *                            ou de serialização do payload.
     */
    public function sendText(SendTextRequest $request): SendTextResponse
    {
        $url = $this->config->baseUrl . self::SEND_TEXT_PATH;

        try {
            $jsonBody = json_encode($request->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Falha ao serializar o payload da requisição.', previous: $exception);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'DeviceToken' => $this->config->deviceToken,
            'Authorization' => 'Bearer ' . $this->config->bearerToken,
        ];

        $result = $this->httpClient->request('POST', $url, $headers, $jsonBody, $this->config->timeoutSeconds);

        return $this->parseResponse($result['statusCode'], $result['body']);
    }

    /**
     * @throws ApiBrasilException
     */
    private function parseResponse(int $statusCode, string $rawBody): SendTextResponse
    {
        /** @var array<string, mixed> $decoded */
        $decoded = [];

        if ($rawBody !== '') {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                // Corpo não-JSON (ex.: página de erro do gateway/proxy); segue com $decoded vazio
                // e usa o HTTP status + corpo bruto para compor a exceção abaixo.
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $decoded['message']
                ?? $decoded['error']
                ?? "A APIBrasil retornou HTTP {$statusCode} sem detalhes adicionais.";

            throw new ApiBrasilException(
                message: is_string($message) ? $message : "Erro HTTP {$statusCode} na APIBrasil.",
                httpStatusCode: $statusCode,
                apiErrorCode: isset($decoded['code']) ? (string) $decoded['code'] : null,
                rawResponseBody: $rawBody,
            );
        }

        return SendTextResponse::fromArray($decoded);
    }
}
