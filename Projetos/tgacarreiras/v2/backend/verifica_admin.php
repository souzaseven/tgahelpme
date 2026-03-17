<?php
/**
 * TGA Carreiras — Verificação de acesso Admin
 * Arquivo: backend/verifica_admin.php
 *
 * Este arquivo NÃO PODE imprimir HTML.
 * Ele apenas valida sessão e redireciona se não estiver logado.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ajuste aqui os nomes das sessões conforme o seu auth_admin.php grava.
// Suporte a chaves comuns:
$adminId =
    (int)($_SESSION['admin_id'] ?? 0);

if ($adminId <= 0) {
    $adminId = (int)($_SESSION['usuario_id'] ?? 0);
}

// Se você usa flags de permissão (ex: admweb/adm_dev), valide aqui também.
// Exemplo (opcional):
// if (empty($_SESSION['admweb']) || (int)$_SESSION['admweb'] !== 1) { ... }

if ($adminId <= 0) {
    // Padrão novo de mensagem: msg/tipo
    $msg  = urlencode('Faça login para acessar o painel.');
    $tipo = urlencode('error');

    header("Location: login.php?msg={$msg}&tipo={$tipo}");
    exit;
}

// Disponibiliza o ID para a página que incluiu (se quiser usar)
$GLOBALS['__ADMIN_ID__'] = $adminId;