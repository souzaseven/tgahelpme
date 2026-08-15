<?php
// Script temporário de diagnóstico. APAGUE este arquivo do servidor
// depois de usar — ele expõe informações internas do ambiente.

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "PHP version: " . PHP_VERSION . "\n";
echo "PHP_VERSION_ID: " . PHP_VERSION_ID . "\n\n";

echo "Suporta enum (PHP 8.1+): " . (PHP_VERSION_ID >= 80100 ? 'SIM' : 'NAO') . "\n";
echo "Suporta readonly properties (PHP 8.1+): " . (PHP_VERSION_ID >= 80100 ? 'SIM' : 'NAO') . "\n";
echo "Suporta constructor promotion (PHP 8.0+): " . (PHP_VERSION_ID >= 80000 ? 'SIM' : 'NAO') . "\n";
echo "Suporta named arguments (PHP 8.0+): " . (PHP_VERSION_ID >= 80000 ? 'SIM' : 'NAO') . "\n\n";

echo "Extensão cURL carregada: " . (extension_loaded('curl') ? 'SIM' : 'NAO') . "\n";
echo "Extensão JSON carregada: " . (extension_loaded('json') ? 'SIM' : 'NAO') . "\n\n";

echo "Diretório atual (__DIR__): " . __DIR__ . "\n";
$bootstrapPath = __DIR__ . '/../bootstrap.php';
echo "bootstrap.php existe? " . (is_file($bootstrapPath) ? 'SIM' : 'NAO') . " ({$bootstrapPath})\n";

$envPath = __DIR__ . '/../.env';
echo ".env existe? " . (is_file($envPath) ? 'SIM' : 'NAO') . " ({$envPath})\n\n";

echo "Tentando carregar as classes da integração...\n";
try {
    require $bootstrapPath;
    echo "bootstrap.php carregado OK.\n";

    $reflectionTargets = [
        \ApiBrasil\Baileys\Dto\PresenceType::class,
        \ApiBrasil\Baileys\Dto\SendTextOptions::class,
        \ApiBrasil\Baileys\Dto\SendTextRequest::class,
        \ApiBrasil\Baileys\Config\ApiBrasilConfig::class,
        \ApiBrasil\Baileys\BaileysMessageService::class,
    ];

    foreach ($reflectionTargets as $class) {
        echo "  - {$class}: " . (class_exists($class) || enum_exists($class) ? 'OK' : 'FALHOU') . "\n";
    }
} catch (\Throwable $e) {
    echo "ERRO ao carregar: " . get_class($e) . " -> " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
