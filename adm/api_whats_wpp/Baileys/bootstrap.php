<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

/**
 * Carrega variáveis de um arquivo .env para o ambiente (getenv/$_ENV),
 * sem dependências externas. Em produção, prefira configurar as
 * variáveis diretamente no servidor/orquestrador (Apache, nginx+PHP-FPM,
 * Docker, etc.) em vez de depender de um arquivo .env em disco.
 */
function loadEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($name) === false && !isset($_ENV[$name])) {
            // Em alguns hospedeiros (ex.: cPanel/CloudLinux) putenv() vem
            // desabilitado por segurança (disable_functions). Por isso
            // sempre gravamos em $_ENV também — env('...') abaixo sabe ler
            // dali como alternativa a getenv().
            if (function_exists('putenv')) {
                @putenv("{$name}={$value}");
            }
            $_ENV[$name] = $value;
        }
    }
}

/**
 * Lê uma variável de ambiente considerando getenv(), $_ENV e $_SERVER —
 * necessário porque nem todo host propaga putenv()/getenv() da mesma forma.
 */
function env(string $name): string|false
{
    $value = getenv($name);

    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
        return (string) $_ENV[$name];
    }

    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return (string) $_SERVER[$name];
    }

    return false;
}

loadEnvFile(__DIR__ . '/.env');
