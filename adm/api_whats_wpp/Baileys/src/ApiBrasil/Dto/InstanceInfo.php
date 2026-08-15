<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Dados da instância (device) retornados pelos endpoints de gerenciamento.
 */
final class InstanceInfo
{
    public function __construct(
        public readonly ?string $instanceName,
        public readonly ?string $instanceId,
        public readonly ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $status = $data['status'] ?? $data['state'] ?? null;

        return new self(
            instanceName: isset($data['instanceName']) && is_string($data['instanceName']) ? $data['instanceName'] : null,
            instanceId: isset($data['instanceId']) && is_string($data['instanceId']) ? $data['instanceId'] : null,
            status: is_string($status) ? $status : null,
        );
    }
}
