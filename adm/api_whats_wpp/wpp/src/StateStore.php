<?php

declare(strict_types=1);

namespace App;

/**
 * Persistência simples em arquivo JSON do último estado conhecido da sessão
 * do WhatsApp (status, QR Code, mensagens). Serve de "cache" local para o
 * painel não depender de reconsultar a API Brasil a cada poll: os webhooks
 * (wh_status, wh_qrcode, wh_connect, wh_message) escrevem aqui, e o
 * front-end lê o estado via api/state.php.
 *
 * Suficiente para uma sessão única rodando localmente. Se o painel crescer
 * para múltiplas sessões ou múltiplos usuários, troque esta classe por uma
 * tabela em banco de dados (a interface pública pode continuar a mesma).
 */
class StateStore
{
    private readonly string $filePath;

    public function __construct(string $dataDir)
    {
        $this->filePath = rtrim($dataDir, '/\\') . '/session_state.json';
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        if (!is_file($this->filePath)) {
            return ['status' => 'idle'];
        }

        $decoded = json_decode((string) file_get_contents($this->filePath), true);

        return is_array($decoded) ? $decoded : ['status' => 'idle'];
    }

    /** @param array<string, mixed> $state */
    public function save(array $state): void
    {
        $dir = dirname($this->filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->filePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Mescla campos parciais no estado atual (não perde o que já estava salvo).
     *
     * @param array<string, mixed> $partial
     * @return array<string, mixed> Estado resultante já mesclado
     */
    public function merge(array $partial): array
    {
        $updated = array_merge($this->read(), $partial);
        $this->save($updated);

        return $updated;
    }

    /**
     * Adiciona uma mensagem ao topo do histórico (mantém as últimas 50).
     *
     * @param array<string, mixed> $message
     */
    public function appendMessage(array $message): void
    {
        $state = $this->read();
        $state['messages'] = $state['messages'] ?? [];
        array_unshift($state['messages'], $message);
        $state['messages'] = array_slice($state['messages'], 0, 50);
        $this->save($state);
    }
}
