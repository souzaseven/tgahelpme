<?php
// backend/auth.php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/conexao.php"; // ✅ conexão central

// ============================================================
// FLAG GLOBAL — MODO DEV (SEM LOGIN TEMPORÁRIO)
// ============================================================
// Quando false → login + CSRF voltam a ser exigidos
define('DEV_SEM_LOGIN', true);

// ============================================================
// JSON PADRÃO
// ============================================================
function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// AUTENTICAÇÃO
// ============================================================
function require_login(): void {

    // 🔓 Bypass temporário (DEV)
    if (defined('DEV_SEM_LOGIN') && DEV_SEM_LOGIN === true) {
        return;
    }

    if (empty($_SESSION["admin_logged"])) {
        json_out([
            "success" => false,
            "error"   => "Não autenticado."
        ], 401);
    }
}

// ============================================================
// CSRF TOKEN
// ============================================================
function csrf_token(): string {
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(16));
    }
    return $_SESSION["csrf"];
}

function require_csrf(): void {

    // 🔓 Bypass temporário (DEV)
    if (defined('DEV_SEM_LOGIN') && DEV_SEM_LOGIN === true) {
        return;
    }

    $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (
        !$token ||
        empty($_SESSION["csrf"]) ||
        !hash_equals($_SESSION["csrf"], $token)
    ) {
        json_out([
            "success" => false,
            "error"   => "CSRF inválido."
        ], 403);
    }
}
