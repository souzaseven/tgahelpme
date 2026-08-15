<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Payload da requisição para POST /api/v2/evolution/instance/create.
 */
final class InstanceCreateRequest
{
    public function __construct(
        public readonly string $instanceName,
        public readonly bool $qrcode = true,
        public readonly bool $homolog = false,
    ) {
    }

    /**
     * @return array{instanceName: string, qrcode: bool, homolog: bool}
     */
    public function toArray(): array
    {
        return [
            'instanceName' => $this->instanceName,
            'qrcode' => $this->qrcode,
            'homolog' => $this->homolog,
        ];
    }
}
