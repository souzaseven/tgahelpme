<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Resposta normalizada para os endpoints de instância
 * (create, connect, connectionState, restart, logout).
 *
 * Cada endpoint devolve um formato um pouco diferente (nem sempre há
 * "instance" ou "qrcode" no corpo). O corpo bruto sempre fica disponível
 * em `raw`; os campos tipados são um "melhor esforço" de mapeamento,
 * ajustável conforme o payload real de cada endpoint for confirmado.
 */
final class InstanceResponse
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly bool $error,
        public readonly ?string $message,
        public readonly ?InstanceInfo $instance,
        public readonly ?QrCodeInfo $qrcode,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $response = is_array($data['response'] ?? null) ? $data['response'] : $data;

        $instanceData = $response['instance'] ?? (isset($response['instanceName']) ? $response : null);
        $qrcodeData = $response['qrcode'] ?? (isset($response['base64']) || isset($response['code']) ? $response : null);

        return new self(
            error: (bool) ($data['error'] ?? false),
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
            instance: InstanceInfo::fromArray(is_array($instanceData) ? $instanceData : null),
            qrcode: QrCodeInfo::fromArray(is_array($qrcodeData) ? $qrcodeData : null),
            raw: $data,
        );
    }
}
