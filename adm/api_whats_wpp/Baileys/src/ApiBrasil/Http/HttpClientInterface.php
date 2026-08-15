<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Http;

/**
 * Abstração mínima de cliente HTTP. Permite injetar outra implementação
 * (ex.: Guzzle, wrapper com retries/circuit breaker) sem alterar o serviço
 * de integração — apenas trocando o que é passado no construtor.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{statusCode: int, body: string}
     */
    public function request(string $method, string $url, array $headers, string $jsonBody, int $timeoutSeconds): array;
}
