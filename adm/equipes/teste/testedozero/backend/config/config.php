<?php

declare(strict_types=1);

/**
 * FASE 1 — FUNDAÇÃO
 * -----------------------------------------------------------------------
 * Ponto central de configuração da aplicação:
 *  - carregamento de variáveis de ambiente (.env);
 *  - timezone;
 *  - exibição/registro de erros;
 *  - tratamento global de erros e exceções não capturadas.
 *
 * Este arquivo NUNCA deve conter credenciais diretamente no código.
 * Todas as credenciais vêm do arquivo .env (fora do controle de versão).
 * -----------------------------------------------------------------------
 */

if (!defined('TGA_ROOT')) {
    // Raiz do projeto (duas pastas acima de backend/config/)
    define('TGA_ROOT', dirname(__DIR__, 2));
}

require_once __DIR__ . '/../helpers/logger.php';

/**
 * Carrega variáveis de um arquivo .env simples (formato CHAVE=VALOR).
 * Não sobrescreve variáveis já definidas no ambiente do servidor/SO.
 */
function carregarEnv(string $caminho): void
{
    if (!is_file($caminho) || !is_readable($caminho)) {
        return;
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }

        if (!str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        // Remove aspas simples/duplas envolventes, se houver.
        if (strlen($valor) >= 2) {
            $primeiro = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];
            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        if ($chave === '') {
            continue;
        }

        if (getenv($chave) === false && !isset($_ENV[$chave])) {
            putenv("{$chave}={$valor}");
            $_ENV[$chave] = $valor;
        }
    }
}

carregarEnv(TGA_ROOT . '/.env');

/**
 * Lê uma variável de ambiente com valor padrão.
 */
function env(string $chave, mixed $padrao = null): mixed
{
    $valor = $_ENV[$chave] ?? getenv($chave);

    if ($valor === false || $valor === null || $valor === '') {
        return $padrao;
    }

    return $valor;
}

if (!defined('APP_ENV')) {
    define('APP_ENV', (string) env('APP_ENV', 'production'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
}

date_default_timezone_set('America/Sao_Paulo');

// --- Tratamento de erros ------------------------------------------------
// Em produção, erros NUNCA são exibidos na tela (evita vazar caminhos,
// queries ou detalhes internos). Todo erro é sempre registrado em log.
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

$diretorioLogs = TGA_ROOT . '/backend/storage/logs';
if (!is_dir($diretorioLogs)) {
    mkdir($diretorioLogs, 0775, true);
}

ini_set('log_errors', '1');
ini_set('error_log', $diretorioLogs . '/php-' . date('Y-m-d') . '.log');

/**
 * Converte erros do PHP (warnings, notices, etc.) em registros de log,
 * sem interromper a execução (mantém o comportamento nativo também).
 */
set_error_handler(function (int $nivel, string $mensagem, string $arquivo, int $linha): bool {
    registrarErro("PHP [{$nivel}]: {$mensagem} em {$arquivo}:{$linha}");

    return false; // false = também deixa o handler nativo do PHP atuar
});

/**
 * Captura qualquer exceção não tratada em toda a aplicação, registra em
 * log e responde de forma segura (sem detalhes internos em produção).
 */
set_exception_handler(function (Throwable $e): void {
    registrarErro('Exceção não tratada: ' . $e->getMessage(), [
        'arquivo' => $e->getFile(),
        'linha'   => $e->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Ocorreu um erro interno. Já fomos notificados.';
    }
});
