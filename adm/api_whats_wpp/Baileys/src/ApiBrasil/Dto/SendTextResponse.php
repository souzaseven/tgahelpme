<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Resposta normalizada do endpoint sendText.
 *
 * O campo `raw` sempre preserva o corpo original decodificado, pois o
 * formato exato de sucesso do gateway APIBrasil pode variar; os demais
 * campos são um "melhor esforço" de mapeamento e podem ser ajustados
 * assim que o payload real de produção for observado.
 *
 * @phpstan-type RawPayload array<string, mixed>
 */
final class SendTextResponse
{
    /**
     * @param RawPayload $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message,
        public readonly ?string $messageId,
        public readonly array $raw,
    ) {
    }

    /**
     * @param RawPayload $data
     */
    public static function fromArray(array $data): self
    {
        $key = $data['key'] ?? null;

        return new self(
            success: ($data['error'] ?? false) === false,
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
            messageId: is_array($key) && isset($key['id']) ? (string) $key['id'] : (isset($data['id']) ? (string) $data['id'] : null),
            raw: $data,
        );
    }
}
