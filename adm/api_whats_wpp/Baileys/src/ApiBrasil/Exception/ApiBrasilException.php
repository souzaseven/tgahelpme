<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Exception;

use RuntimeException;
use Throwable;

/**
 * Exceção lançada quando a API do APIBrasil retorna um erro (HTTP 4xx/5xx)
 * ou quando o corpo de erro pôde ser interpretado.
 */
final class ApiBrasilException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatusCode,
        private readonly ?string $apiErrorCode = null,
        private readonly ?string $rawResponseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getApiErrorCode(): ?string
    {
        return $this->apiErrorCode;
    }

    public function getRawResponseBody(): ?string
    {
        return $this->rawResponseBody;
    }
}
