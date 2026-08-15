<?php

declare(strict_types=1);

namespace ApiBrasil\Baileys\Config;

use RuntimeException;

/**
 * Configuração de acesso à APIBrasil. Os valores nunca devem ser hardcoded
 * no código-fonte: construa esta classe a partir de variáveis de ambiente
 * via ApiBrasilConfig::fromEnv().
 */
final class ApiBrasilConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $bearerToken,
        public readonly string $deviceToken,
        public readonly int $timeoutSeconds = 30,
    ) {
    }

    public static function fromEnv(): self
    {
        // env() (definida em bootstrap.php) cai para $_ENV/$_SERVER quando
        // getenv()/putenv() não propagam variáveis nesse ambiente (comum em
        // hospedagens compartilhadas que desabilitam putenv() por segurança).
        $readEnv = function_exists('env') ? 'env' : 'getenv';

        $baseUrl = $readEnv('APIBRASIL_BASE_URL');
        $bearerToken = $readEnv('APIBRASIL_BEARER_TOKEN');
        $deviceToken = $readEnv('APIBRASIL_DEVICE_TOKEN');
        $timeout = $readEnv('APIBRASIL_TIMEOUT_SECONDS');

        if ($bearerToken === false || $bearerToken === '' || $deviceToken === false || $deviceToken === '') {
            throw new RuntimeException(
                'As variáveis de ambiente APIBRASIL_BEARER_TOKEN e APIBRASIL_DEVICE_TOKEN são obrigatórias. '
                . 'Configure-as no seu .env (veja .env.example).'
            );
        }

        return new self(
            baseUrl: rtrim($baseUrl !== false && $baseUrl !== '' ? $baseUrl : 'https://gateway.apibrasil.io', '/'),
            bearerToken: $bearerToken,
            deviceToken: $deviceToken,
            timeoutSeconds: $timeout !== false && $timeout !== '' ? (int) $timeout : 30,
        );
    }
}
