<?php
// ============================================================
// controle_pausa.php (v3.1 - Leve / sincronizado com Movidesk/Evolux)
// ============================================================
// 🔹 Mantém estado temporário em JSON local
// 🔹 Limpa automaticamente cache após 2h de inatividade
// 🔹 Não grava nada no banco
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
// 📁 Caminho do cache
// ============================================================
$cacheDir = __DIR__ . '/tmp';
$cacheFile = $cacheDir . '/estado_pausas.json';

if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);

// ============================================================
// 🔧 Funções auxiliares
// ============================================================
function lerEstado(string $arquivo): array {
  if (!file_exists($arquivo)) {
    return ['participantes' => [], 'ultima_atualizacao' => null];
  }

  $json = file_get_contents($arquivo);
  $data = json_decode($json, true);

  // Corrige caso JSON inválido
  if (!is_array($data)) {
    return ['participantes' => [], 'ultima_atualizacao' => null];
  }

  // Limpeza automática se inativo > 2 horas
  $ultima = strtotime($data['ultima_atualizacao'] ?? '1970-01-01 00:00:00');
  if ($ultima && (time() - $ultima) > 7200) { // 2 horas = 7200 segundos
    file_put_contents($arquivo, json_encode(['participantes' => [], 'ultima_atualizacao' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT));
    return ['participantes' => [], 'ultima_atualizacao' => date('Y-m-d H:i:s')];
  }

  return $data;
}

function salvarEstado(string $arquivo, array $dados): void {
  $dados['ultima_atualizacao'] = date('Y-m-d H:i:s');
  file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function resposta(bool $ok, string $msg, array $extra = []): void {
  echo json_encode(array_merge(['success' => $ok, 'mensagem' => $msg], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// ============================================================
// 📡 Roteamento por ação
// ============================================================
$acao = $_GET['acao'] ?? 'get_estado';
$estado = lerEstado($cacheFile);
$participantes = $estado['participantes'] ?? [];

// ============================================================
// 🧩 get_estado
// ============================================================
if ($acao === 'get_estado') {
  resposta(true, 'Estado atual retornado com sucesso.', [
    'estado' => $estado,
  ]);
}

// ============================================================
// ➕ entrar_fila
// ============================================================
if ($acao === 'entrar_fila') {
  $body = json_decode(file_get_contents('php://input'), true);
  $nome = trim($body['nome'] ?? '');

  if ($nome === '') resposta(false, 'Nome não informado.');

  // Se já estiver na lista, não duplica
  foreach ($participantes as $p) {
    if (strcasecmp($p['nome'], $nome) === 0) {
      resposta(true, 'Usuário já está na lista.', ['estado' => $estado]);
    }
  }

  $participantes[] = [
    'nome' => $nome,
    'status' => 'espera',
  ];

  salvarEstado($cacheFile, ['participantes' => $participantes]);
  resposta(true, 'Adicionado à fila de espera.', ['estado' => lerEstado($cacheFile)]);
}

// ============================================================
// 🔙 voltar_disponivel
// ============================================================
if ($acao === 'voltar_disponivel') {
  $body = json_decode(file_get_contents('php://input'), true);
  $nome = trim($body['nome'] ?? '');

  if ($nome === '') resposta(false, 'Nome não informado.');

  $atualizado = false;
  foreach ($participantes as &$p) {
    if (strcasecmp($p['nome'], $nome) === 0) {
      $p['status'] = 'disponivel';
      $atualizado = true;
    }
  }
  unset($p);

  if (!$atualizado) {
    $participantes[] = ['nome' => $nome, 'status' => 'disponivel'];
  }

  salvarEstado($cacheFile, ['participantes' => $participantes]);
  resposta(true, 'Marcado como disponível.', ['estado' => lerEstado($cacheFile)]);
}

// ============================================================
// ☕ forcar_pausa (apenas admin / id 6)
// ============================================================
if ($acao === 'forcar_pausa') {
  $body = json_decode(file_get_contents('php://input'), true);
  $nome = trim($body['nome'] ?? '');

  if ($nome === '') resposta(false, 'Nome não informado.');

  $achou = false;
  foreach ($participantes as &$p) {
    if (strcasecmp($p['nome'], $nome) === 0) {
      $p['status'] = 'pausa';
      $achou = true;
    }
  }
  unset($p);

  if (!$achou) {
    $participantes[] = ['nome' => $nome, 'status' => 'pausa'];
  }

  salvarEstado($cacheFile, ['participantes' => $participantes]);
  resposta(true, 'Usuário forçado para pausa.', ['estado' => lerEstado($cacheFile)]);
}

// ============================================================
// 🧹 Limpeza manual (admin)
// ============================================================
if ($acao === 'limpar_cache') {
  if (file_exists($cacheFile)) {
    unlink($cacheFile);
  }
  resposta(true, 'Cache de pausas limpo manualmente.');
}

// ============================================================
// ❌ Ação inválida
// ============================================================
resposta(false, 'Ação inválida ou não suportada.');
