<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Dto;

/**
 * Payload da requisição para POST /api/v2/evolution/message/sendText.
 */
final class SendTextRequest
{
    public function __construct(
        public readonly string $number,
        public readonly string $text,
        public readonly SendTextOptions $options = new SendTextOptions(),
        public readonly bool $homolog = false,
    ) {
    }

    /**
     * @return array{number: string, text: string, options: array{delay:int,presence:string}, homolog: bool}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'text' => $this->text,
            'options' => $this->options->toArray(),
            'homolog' => $this->homolog,
        ];
    }
}
