<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Payload mínimo usado por /instance/restart e /instance/logout,
 * que só esperam o flag "homolog" no corpo.
 */
final class HomologFlagRequest
{
    public function __construct(
        public readonly bool $homolog = false,
    ) {
    }

    /**
     * @return array{homolog: bool}
     */
    public function toArray(): array
    {
        return ['homolog' => $this->homolog];
    }
}
