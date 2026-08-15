<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 simplificado (sem Composer) para o namespace ApiBrasil\Baileys.
 * Se o projeto já usa Composer, substitua este require por vendor/autoload.php
 * e mapeie o namespace em composer.json ("autoload.psr-4").
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'ApiBrasil\\Baileys\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/ApiBrasil/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
