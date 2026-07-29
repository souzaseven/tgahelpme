<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader simples (sem Composer) para o namespace App\.
 * Mapeia App\Core\X       -> backend/Core/X.php
 *        App\Models\X     -> backend/Models/X.php
 *        App\Helpers\X    -> backend/Helpers/X.php
 */
final class Autoloader
{
    private const NAMESPACE_PREFIX = 'App\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $class): void
    {
        if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::NAMESPACE_PREFIX));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        $file = BACKEND_PATH . DIRECTORY_SEPARATOR . $relativePath;

        if (is_file($file)) {
            require_once $file;
        }
    }

    private function __construct()
    {
    }
}
