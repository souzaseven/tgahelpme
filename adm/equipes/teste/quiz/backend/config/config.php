<?php

declare(strict_types=1);

/**
 * Configurações gerais da aplicação. Carregado pelo bootstrap.php.
 */

const APP_NAME = 'TGA Academy';
const APP_ENV = 'local'; // 'local' | 'producao'
const APP_URL = 'http://localhost';

const UPLOAD_PATH = BASE_PATH . '/uploads';
const MAX_UPLOAD_SIZE_MB = 20;
const ALLOWED_UPLOAD_EXTENSIONS = ['pdf', 'docx', 'txt', 'html', 'md'];

// Credenciais de banco de dados (arquivo git-ignorado, ver env.example.php)
$envFile = __DIR__ . '/env.php';
if (!is_file($envFile)) {
    throw new RuntimeException(
        'Arquivo backend/config/env.php não encontrado. Copie env.example.php para env.php e preencha as credenciais.'
    );
}
require_once $envFile;
