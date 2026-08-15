<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys;

use ApiBrasil\Baileys\Config\ApiBrasilConfig;
use ApiBrasil\Baileys\Dto\HomologFlagRequest;
use ApiBrasil\Baileys\Dto\InstanceCreateRequest;
use ApiBrasil\Baileys\Dto\InstanceResponse;
use ApiBrasil\Baileys\Exception\ApiBrasilException;
use ApiBrasil\Baileys\Http\CurlHttpClient;
use ApiBrasil\Baileys\Http\HttpClientInterface;
use JsonException;
use RuntimeException;

/**
 * Serviço dedicado ao gerenciamento do ciclo de vida da instância
 * (device) WhatsApp/Baileys na APIBrasil: criação, obtenção de QR code
 * para pareamento, consulta de status, reinício e logout.
 *
 * Configuração e cliente HTTP são injetados via construtor, seguindo o
 * mesmo padrão do BaileysMessageService.
 */
final class BaileysInstanceService
{
    private const CREATE_PATH = '/api/v2/evolution/instance/create';
    private const CONNECT_PATH = '/api/v2/evolution/instance/connect';
    private const CONNECTION_STATE_PATH = '/api/v2/evolution/instance/connectionState';
    private const RESTART_PATH = '/api/v2/evolution/instance/restart';
    private const LOGOUT_PATH = '/api/v2/evolution/instance/logout';

    public function __construct(
        private readonly ApiBrasilConfig $config,
        private readonly HttpClientInterface $httpClient = new CurlHttpClient(),
    ) {
    }

    /**
     * Cria uma nova instância (device) e, opcionalmente, já retorna o QR
     * code inicial para pareamento. Uso único por device — chamar de novo
     * pode criar uma instância duplicada, dependendo da conta.
     */
    public function create(InstanceCreateRequest $request): InstanceResponse
    {
        return $this->call('POST', self::CREATE_PATH, $request->toArray());
    }

    /**
     * Obtém um novo QR code para conectar/reconectar o device já existente
     * (associado ao DeviceToken configurado). É o endpoint indicado para o
     * botão "Gerar QR Code" da tela — não cria uma instância nova.
     */
    public function connect(): InstanceResponse
    {
        return $this->call('GET', self::CONNECT_PATH, null);
    }

    /**
     * Consulta o estado atual da conexão (ex.: "connecting", "open", "close").
     */
    public function connectionState(): InstanceResponse
    {
        return $this->call('GET', self::CONNECTION_STATE_PATH, null);
    }

    /**
     * Reinicia a instância no gateway.
     */
    public function restart(bool $homolog = false): InstanceResponse
    {
        return $this->call('POST', self::RESTART_PATH, (new HomologFlagRequest($homolog))->toArray());
    }

    /**
     * Desconecta (logout) o WhatsApp vinculado ao device.
     */
    public function logout(bool $homolog = false): InstanceResponse
    {
        return $this->call('DELETE', self::LOGOUT_PATH, (new HomologFlagRequest($homolog))->toArray());
    }

    /**
     * @param array<string, mixed>|null $body
     * @throws ApiBrasilException quando a API responde com status 4xx/5xx.
     * @throws RuntimeException   em falhas de comunicação ou serialização.
     */
    private function call(string $method, string $path, ?array $body): InstanceResponse
    {
        $url = $this->config->baseUrl . $path;

        $jsonBody = '';
        if ($body !== null) {
            try {
                $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Falha ao serializar o payload da requisição.', previous: $exception);
            }
        }

        $headers = [
            'Content-Type' => 'application/json',
            'DeviceToken' => $this->config->deviceToken,
            'Authorization' => 'Bearer ' . $this->config->bearerToken,
        ];

        $result = $this->httpClient->request($method, $url, $headers, $jsonBody, $this->config->timeoutSeconds);

        return $this->parseResponse($result['statusCode'], $result['body']);
    }

    /**
     * @throws ApiBrasilException
     */
    private function parseResponse(int $statusCode, string $rawBody): InstanceResponse
    {
        /** @var array<string, mixed> $decoded */
        $decoded = [];

        if ($rawBody !== '') {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                // Corpo não-JSON; segue com $decoded vazio e usa o HTTP status
                // + corpo bruto para compor a exceção abaixo.
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

        return InstanceResponse::fromArray($decoded);
    }
}
