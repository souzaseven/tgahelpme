<?php
/**
 * Cliente HTTP genérico para API Evolux
 * Autor: Anderson de Souza
 */

class EvoluxClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->token   = $config['token'];
        $this->timeout = $config['timeout'] ?? 15;
    }

    /**
     * Requisição GET
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'token: ' . $this->token,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Erro CURL: ' . $error);
        }

        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response'  => json_decode($response, true)
        ];
    }
}
