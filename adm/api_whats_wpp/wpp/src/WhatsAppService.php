<?php

declare(strict_types=1);

namespace App;

/**
 * Camada de domínio sobre o ApiBrasilClient, específica para o serviço
 * "WhatsApp - WPP" da API Brasil. Concentra os nomes de endpoints e o shape
 * dos payloads em um único lugar.
 *
 * ATENÇÃO: os campos de start() foram confirmados na documentação oficial.
 * Os de sendText() ainda são uma estimativa (baseada no padrão dos demais
 * endpoints) — ajuste aqui assim que o console de testes da API Brasil
 * retornar um exemplo real de request/response.
 */
class WhatsAppService
{
    public function __construct(private readonly ApiBrasilClient $client)
    {
    }

    /**
     * Inicia (ou retoma) uma sessão do WhatsApp e solicita o QR Code de pareamento.
     *
     * @param array<string, mixed> $overrides Sobrescreve/estende campos padrão do payload
     * @return array<string, mixed>
     */
    public function start(string $session, array $overrides = []): array
    {
        $payload = array_merge([
            'session' => $session,
            'qrcode' => true,
            'auto_close' => 120000,
            'force_clear_cache' => false,
            'headless' => 'new',
            'use_chrome' => true,
        ], $overrides);

        return $this->client->post('whatsapp/start', $payload);
    }

    /**
     * Envia uma mensagem de texto simples.
     *
     * TODO: confirmar o nome exato do endpoint (sendText/sendMessage) e dos
     * campos do payload no console de testes da API Brasil.
     */
    public function sendText(string $session, string $number, string $message): array
    {
        return $this->client->post('whatsapp/sendText', [
            'session' => $session,
            'number' => $number,
            'text' => $message,
        ]);
    }
}
