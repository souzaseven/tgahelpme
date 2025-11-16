<?php
// ============================================================
// logout.php (v3.0)
// ============================================================
// 🔹 Encerra a sessão do operador ou admin (ID 6)
// 🔹 Retorna JSON padronizado
// 🔹 Compatível com fetch() usado em main.js
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ============================================================
// 🔐 Controle de sessão
// ============================================================
session_start();

$id_usuario = $_SESSION['id_usuario'] ?? null;
$nome_usuario = $_SESSION['nome_usuario'] ?? null;

// Finaliza sessão
session_unset();
session_destroy();

// Remove cookies de sessão, se existirem
if (isset($_COOKIE[session_name()])) {
  setcookie(session_name(), '', time() - 3600, '/');
}

// ============================================================
// 🧠 Mensagem final com contexto
// ============================================================
echo json_encode([
  'success' => true,
  'mensagem' => 'Sessão encerrada com sucesso.',
  'usuario' => $nome_usuario ?: 'desconhecido',
  'id' => $id_usuario ?: null,
  'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

exit;
