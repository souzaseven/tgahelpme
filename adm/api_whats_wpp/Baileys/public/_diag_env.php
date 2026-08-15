<?php
// Script temporário — mostra só um trecho mascarado dos tokens para
// confirmar que o .env está sendo lido corretamente, sem expor o valor
// inteiro. APAGUE este arquivo do servidor depois de usar.

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

function mask(string|false $value): string
{
    if ($value === false || $value === '') {
        return '(vazio / não definido)';
    }

    $len = strlen($value);
    $head = substr($value, 0, 6);
    $tail = substr($value, -4);

    return "{$head}...{$tail} (tamanho: {$len})";
}

$readEnv = function_exists('env') ? 'env' : 'getenv';

echo "APIBRASIL_BASE_URL: " . var_export($readEnv('APIBRASIL_BASE_URL'), true) . "\n";
echo "APIBRASIL_BEARER_TOKEN: " . mask($readEnv('APIBRASIL_BEARER_TOKEN')) . "\n";
echo "APIBRASIL_DEVICE_TOKEN: " . mask($readEnv('APIBRASIL_DEVICE_TOKEN')) . "\n";
echo "APIBRASIL_TIMEOUT_SECONDS: " . var_export($readEnv('APIBRASIL_TIMEOUT_SECONDS'), true) . "\n";

echo "\nFunção putenv() existe? " . (function_exists('putenv') ? 'SIM' : 'NAO') . "\n";
echo "Função env() customizada carregada? " . (function_exists('env') ? 'SIM' : 'NAO') . "\n";
