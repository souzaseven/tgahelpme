<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Http;

use RuntimeException;

/**
 * Implementação padrão do HttpClientInterface, usando a extensão cURL
 * nativa do PHP (sem dependências externas).
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers, string $jsonBody, int $timeoutSeconds): array
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException('Falha ao inicializar cURL.');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = "{$name}: {$value}";
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        // Só anexa corpo se houver payload (GET/DELETE sem body, como
        // connect/connectionState, não devem enviar Content-Length: 0 estranho).
        if ($jsonBody !== '') {
            $options[CURLOPT_POSTFIELDS] = $jsonBody;
        }

        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);

            throw new RuntimeException("Falha de comunicação com a APIBrasil (cURL #{$errno}): {$error}");
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'statusCode' => $statusCode,
            'body' => (string) $body,
        ];
    }
}
