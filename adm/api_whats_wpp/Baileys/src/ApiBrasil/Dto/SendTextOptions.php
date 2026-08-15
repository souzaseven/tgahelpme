<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Opções adicionais do envio de texto: delay de digitação simulado
 * e presença exibida ao contato antes do envio.
 */
final class SendTextOptions
{
    public function __construct(
        public readonly int $delay = 1,
        public readonly PresenceType $presence = PresenceType::Composing,
    ) {
    }

    /**
     * @return array{delay: int, presence: string}
     */
    public function toArray(): array
    {
        return [
            'delay' => $this->delay,
            'presence' => $this->presence->value,
        ];
    }
}
