<?php
require_once __DIR__ . '/conexao.php';

header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   VALIDAÇÕES BÁSICAS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Método não permitido'
  ]);
  exit;
}

if (!validateCSRF()) {
  http_response_code(403);
  echo json_encode([
    'success' => false,
    'message' => 'CSRF inválido'
  ]);
  exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

try {

  if ($action !== 'summary') {
    throw new Exception('Ação inválida');
  }

/* =====================================================
   KPIs — LOGINS WEB
===================================================== */
$logins_total = (int)$pdo->query("
  SELECT COUNT(*) 
  FROM clientes_web_login
")->fetchColumn();

$logins_ativos = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE UPPER(status) = 'ATIVO'
")->fetchColumn();

/* 🔴 NOVO — LOGINS INATIVOS */
$logins_inativos = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE UPPER(status) = 'INATIVO'
")->fetchColumn();

$logins_com_exe = (int)$pdo->query("
  SELECT COUNT(*)
  FROM clientes_web_login
  WHERE possui_exe = 1
")->fetchColumn();

  /* =====================================================
     KPIs — USUÁRIOS WEB (OFICIAL)
     🔒 Fonte única da verdade
  ===================================================== */

  /* Cadastros (empresas) */
  $usuarios_web_cadastros = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_tga_web_usuarios
  ")->fetchColumn();

  /* Usuários ATIVOS (soma real) */
  $usuarios_web_ativos = (int)$pdo->query("
    SELECT COALESCE(SUM(qtd_usuarios), 0)
    FROM clientes_tga_web_usuarios
    WHERE UPPER(status) = 'ATIVO'
  ")->fetchColumn();

  /* Usuários INATIVOS (soma real) */
  $usuarios_web_inativos = (int)$pdo->query("
    SELECT COALESCE(SUM(qtd_usuarios), 0)
    FROM clientes_tga_web_usuarios
    WHERE UPPER(status) = 'INATIVO'
  ")->fetchColumn();

  /* Total de usuários (ativos + inativos) */
  $usuarios_web_total = $usuarios_web_ativos + $usuarios_web_inativos;

  /* =====================================================
     KPIs — MOBILE
  ===================================================== */
  $fv_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'FV_SMART_CLIENT'
  ")->fetchColumn();

  $api_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_mobile
    WHERE tipo_acesso = 'API_FORCA_DE_VENDA'
  ")->fetchColumn();

  /* =====================================================
     KPIs — WHATSAPP
  ===================================================== */
  $whatsapp_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_api_whats
  ")->fetchColumn();

  /* =====================================================
     KPIs — PDV OFF
  ===================================================== */
  $pdvoff_total = (int)$pdo->query("
    SELECT COUNT(*)
    FROM clientes_web_pdvoff
  ")->fetchColumn();

  /* =====================================================
     ÚLTIMOS 3 — LOGINS WEB
  ===================================================== */
  $last_logins = $pdo->query("
    SELECT
      id,
      codigo_cliente,
      nome_cliente,
      caminho_acesso,
      versao_padrao,
      status,
      DATE_FORMAT(
        COALESCE(atualizado_em, criado_em),
        '%d/%m/%Y, %H:%i:%s'
      ) AS atualizado_em
    FROM clientes_web_login
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — USUÁRIOS WEB
  ===================================================== */
  $last_usuarios_web = $pdo->query("
    SELECT
      id,
      nome_empresa,
      codigo_empresa,
      qtd_usuarios,
      observacao,
      status,
      DATE_FORMAT(
        COALESCE(atualizado_em, criado_em),
        '%d/%m/%Y, %H:%i:%s'
      ) AS atualizado_em
    FROM clientes_tga_web_usuarios
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — MOBILE
  ===================================================== */
  $last_mobile = $pdo->query("
    SELECT
      id,
      cod_cliente,
      cliente,
      acesso_server,
      tipo_acesso,
      observacao,
      DATE_FORMAT(
        COALESCE(atualizado_em, criado_em),
        '%d/%m/%Y, %H:%i:%s'
      ) AS atualizado_em
    FROM clientes_web_mobile
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — WHATSAPP
  ===================================================== */
  $last_whatsapp = $pdo->query("
    SELECT
      id,
      cod_cliente,
      cliente,
      acesso_server,
      tipo_acesso,
      observacao,
      DATE_FORMAT(
        COALESCE(atualizado_em, criado_em),
        '%d/%m/%Y, %H:%i:%s'
      ) AS atualizado_em
    FROM clientes_web_api_whats
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     ÚLTIMOS 3 — PDV OFF
  ===================================================== */
  $last_pdvoff = $pdo->query("
    SELECT
      id,
      cod_cliente,
      cliente,
      acesso_server,
      tipo_acesso,
      observacao,
      DATE_FORMAT(
        COALESCE(atualizado_em, criado_em),
        '%d/%m/%Y, %H:%i:%s'
      ) AS atualizado_em
    FROM clientes_web_pdvoff
    ORDER BY COALESCE(atualizado_em, criado_em) DESC
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  /* =====================================================
     RESPONSE FINAL
  ===================================================== */
  echo json_encode([
    'success' => true,
    'kpis' => [
      /* Logins */
      'logins_total'        => $logins_total,
      'logins_ativos'       => $logins_ativos,
'logins_inativos' => $logins_inativos,
      'logins_com_exe'      => $logins_com_exe,

      /* Usuários Web */
      'usuarios_web_total'      => $usuarios_web_total,
      'usuarios_web_cadastros' => $usuarios_web_cadastros,
      'usuarios_web_ativos'    => $usuarios_web_ativos,
      'usuarios_web_inativos'  => $usuarios_web_inativos,

      /* Outros */
      'fv_total'            => $fv_total,
      'api_total'           => $api_total,
      'whatsapp_total'      => $whatsapp_total,
      'pdvoff_total'        => $pdvoff_total
    ],
    'last_logins'       => $last_logins,
    'last_usuarios_web' => $last_usuarios_web,
    'last_mobile'       => $last_mobile,
    'last_whatsapp'     => $last_whatsapp,
    'last_pdvoff'       => $last_pdvoff
  ]);
  exit;

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}
