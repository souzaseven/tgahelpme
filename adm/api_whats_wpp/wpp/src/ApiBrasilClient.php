<?php

declare(strict_types=1);

namespace App;

/**
 * Exceção lançada para qualquer erro retornado pela API Brasil,
 * seja por status HTTP 4xx/5xx ou pelo payload de erro { "error": true, ... }.
 */
class ApiBrasilException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpCode,
        private readonly ?string $apiCode = null
    ) {
        parent::__construct($message);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }
}

/**
 * Cliente HTTP dedicado ao gateway da API Brasil (v2).
 * Centraliza autenticação (Bearer + DeviceToken), timeout e tratamento de erros
 * para que os serviços de domínio (ex: WhatsAppService) não repitam esse código.
 */
class ApiBrasilClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $bearerToken,
        private readonly string $deviceToken,
        private readonly int $timeoutSeconds = 30
    ) {
    }

    /**
     * Executa uma requisição POST autenticada contra um endpoint do gateway.
     *
     * @param string $endpoint Caminho relativo, ex: "whatsapp/start"
     * @param array<string, mixed> $payload Corpo da requisição
     * @return array<string, mixed> Resposta decodificada
     * @throws ApiBrasilException Em caso de erro de conexão, HTTP ou de negócio
     */
    public function post(string $endpoint, array $payload = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'DeviceToken: ' . $this->deviceToken,
                'Authorization: Bearer ' . $this->bearerToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $rawResponse = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($rawResponse === false || $curlError !== '') {
            throw new ApiBrasilException("Falha de conexão com a API Brasil: {$curlError}", 0);
        }

        $decoded = json_decode((string) $rawResponse, true);

        if (!is_array($decoded)) {
            throw new ApiBrasilException('Resposta inválida da API Brasil (JSON malformado).', $httpCode);
        }

        $hasErrorFlag = ($decoded['error'] ?? false) === true;

        if ($httpCode >= 400 || $hasErrorFlag) {
            $message = (string) ($decoded['message'] ?? 'Erro desconhecido na API Brasil.');
            $code = isset($decoded['code']) ? (string) $decoded['code'] : null;

            throw new ApiBrasilException($message, $httpCode, $code);
        }

        return $decoded;
    }
}
