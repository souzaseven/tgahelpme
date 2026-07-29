<?php

declare(strict_types=1);

/**
 * Ponto de entrada comum: toda página pública (admin, aluno, api, cursos,
 * quizzes, provas, index) começa com require deste arquivo.
 */

error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

const BACKEND_PATH = __DIR__;
define('BASE_PATH', dirname(__DIR__));

/**
 * URL base do projeto (ex.: "" na raiz do domínio, ou "/adm/equipes/quiz"
 * quando publicado em subpasta). Calculado comparando o DOCUMENT_ROOT do
 * servidor com o caminho físico do projeto, para funcionar em qualquer
 * ambiente sem configuração manual.
 */
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$projectRoot = str_replace('\\', '/', BASE_PATH);
if ($documentRoot !== false) {
    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $baseUrl = str_starts_with($projectRoot, $documentRoot)
        ? substr($projectRoot, strlen($documentRoot))
        : '';
} else {
    $baseUrl = '';
}
define('BASE_URL', rtrim($baseUrl, '/'));
unset($documentRoot, $projectRoot, $baseUrl);

require_once BACKEND_PATH . '/Core/Autoloader.php';
App\Core\Autoloader::register();

require_once BACKEND_PATH . '/config/config.php';

// display_errors depende do ambiente definido em config.php
ini_set('display_errors', APP_ENV === 'local' ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
    ]);
}
