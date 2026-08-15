<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Dados do QR code retornados pelos endpoints de instância (create/connect).
 */
final class QrCodeInfo
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $base64,
        public readonly ?string $pairingCode,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null || (!isset($data['base64']) && !isset($data['code']))) {
            return null;
        }

        return new self(
            code: isset($data['code']) && is_string($data['code']) ? $data['code'] : null,
            base64: isset($data['base64']) && is_string($data['base64']) ? $data['base64'] : null,
            pairingCode: isset($data['pairingCode']) && is_string($data['pairingCode']) ? $data['pairingCode'] : null,
        );
    }
}
