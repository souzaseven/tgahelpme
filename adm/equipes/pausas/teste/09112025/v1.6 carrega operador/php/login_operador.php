<?php
// ============================================================
// login_operador.php  (v1.2 - CORRIGIDO)
// - Valida login de operador comum:  ?acao=validar_operador   (POST JSON: {nome})
// - Valida login de admin:          ?acao=login_admin        (POST JSON: {nome, senha})
// - Retorna JSON padronizado
// - Usa conexao.php (PDO)
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . '/conexao.php';

// ------------------------------------------------------------
// 🔧 CONFIGURAÇÕES
// ------------------------------------------------------------
$ADMIN_USER_CANON  = 'anderson'; // APENAS minúsculo
$ADMIN_PASS_HASH   = password_hash('soueu', PASSWORD_DEFAULT); // SENHA: soueu

// ------------------------------------------------------------
// 🛠️ Helpers
// ------------------------------------------------------------
function lerJsonBody(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function normalizarNome(string $nome): string {
  $nome = trim($nome);
  // Deixa “TÍTULO” com iniciais maiúsculas, restante minúsculo
  $nome = mb_strtolower($nome, 'UTF-8');
  return mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8');
}

function tableExists(PDO $pdo, string $tabela): bool {
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tabela));
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) { return false; }
}

function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool {
  try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
    $stmt->execute([$coluna]);
    return (bool)$stmt->fetch();
  } catch (Throwable $e) { return false; }
}

function registrarTentativa(PDO $pdo, array $dados): void {
  if (!tableExists($pdo, 'logs_acesso')) return;
  try {
    $sql = "INSERT INTO logs_acesso
            (usuario_tentado, senha_tentada, ip_origem, pagina, sucesso, data_tentativa)
            VALUES (:usuario_tentado, :senha_tentada, :ip_origem, :pagina, :sucesso, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':usuario_tentado' => $dados['usuario_tentado'] ?? null,
      ':senha_tentada'   => $dados['senha_tentada']   ?? null,
      ':ip_origem'       => $dados['ip_origem']       ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
      ':pagina'          => $dados['pagina']          ?? 'login_operador.php',
      ':sucesso'         => !empty($dados['sucesso']) ? 1 : 0,
    ]);
  } catch (Throwable $e) {
    // Silencioso
  }
}

function jsonOk(array $extra = []) {
  echo json_encode(array_merge(['success' => true], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function jsonErro(string $msg, array $extra = [], int $http = 200) {
  http_response_code($http);
  echo json_encode(array_merge(['success' => false, 'error' => $msg], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// ------------------------------------------------------------
// 🔀 Roteamento por ação
// ------------------------------------------------------------
$acao = $_GET['acao'] ?? '';

switch ($acao) {

  // ----------------------------------------------------------
  // ✅ Validar Operador Comum
  // ----------------------------------------------------------
  case 'validar_operador': {
    $body = lerJsonBody();
    $nomeInformado = trim((string)($body['nome'] ?? ''));

    if ($nomeInformado === '') {
      jsonErro('Nome do operador não informado.');
    }

    $nomeCanon = normalizarNome($nomeInformado);
    $ok = false;

    // 🔧 CORREÇÃO: Se for "anderson" em qualquer case, pede senha
    if (strtolower($nomeInformado) === 'anderson') {
      jsonErro('Para acessar como admin, use a senha.', ['requer_senha' => true]);
    }

    // 1) Se existir tabela de usuários, tenta validar lá
    if (tableExists($pdo, 'usuarios')) {
      $colNome = colunaExiste($pdo, 'usuarios', 'usuario') ? 'usuario' :
                 (colunaExiste($pdo, 'usuarios', 'nome') ? 'nome' : null);

      if ($colNome) {
        $sql = "SELECT $colNome AS nome
                  FROM usuarios
                 WHERE $colNome = :nome
                   AND (ativo IS NULL OR ativo = 1)
                 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nome' => $nomeCanon]);
        $row = $stmt->fetch();
        if ($row) $ok = true;
      }
    }

    // 2) Fallback: aceita qualquer nome com mínimo de 3 caracteres
    if (!$ok && mb_strlen($nomeCanon, 'UTF-8') >= 3) {
      $ok = true;
    }

    registrarTentativa($pdo, [
      'usuario_tentado' => $nomeCanon,
      'senha_tentada'   => null,
      'sucesso'         => $ok ? 1 : 0
    ]);

    if ($ok) {
      jsonOk([
        'nome_canonico' => $nomeCanon,
        'role'          => 'operador'
      ]);
    } else {
      jsonErro('Operador não encontrado ou inativo.');
    }
  }

  // ----------------------------------------------------------
  // 🔐 Login de Admin
  // ----------------------------------------------------------
  case 'login_admin': {
    $body  = lerJsonBody();
    $nome = trim((string)($body['nome'] ?? ''));
    $senha = (string)($body['senha'] ?? '');

    if ($nome === '') jsonErro('Nome não informado.');
    if ($senha === '') jsonErro('Informe a senha.');

    // 🔧 CORREÇÃO: Só permite "anderson" em minúsculo
    if (strtolower($nome) !== 'anderson') {
      jsonErro('Acesso admin permitido apenas para usuário específico.');
    }

    $sucesso = false;
    $usuarioEncontrado = null;

    // 1) Se existir tabela de usuários com admin, prioriza banco
    if (tableExists($pdo, 'usuarios')) {
      $colNome   = colunaExiste($pdo, 'usuarios', 'usuario') ? 'usuario' :
                   (colunaExiste($pdo, 'usuarios', 'nome') ? 'nome' : null);
      $colSenha  = colunaExiste($pdo, 'usuarios', 'senha_hash') ? 'senha_hash' :
                   (colunaExiste($pdo, 'usuarios', 'senha') ? 'senha' : null);
      $colAdmin  = colunaExiste($pdo, 'usuarios', 'is_admin') ? 'is_admin' : null;

      if ($colNome && $colSenha) {
        $sql = "SELECT $colNome AS nome, $colSenha AS senha, " . ($colAdmin ? "$colAdmin AS is_admin" : "1 AS is_admin") . "
                  FROM usuarios
                 WHERE LOWER($colNome) = LOWER(:nome)
                 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        $usuarioEncontrado = $stmt->fetch();
        
        if ($usuarioEncontrado) {
          // Tenta password_verify; se falhar, compara texto puro (compat retro)
          $verificado = password_verify($senha, $usuarioEncontrado['senha']) || ($usuarioEncontrado['senha'] === $senha);
          if ($verificado && (int)$usuarioEncontrado['is_admin'] === 1) {
            $sucesso = true;
          }
        }
      }
    }

    // 2) Fallback: valida com hash local configurado no topo
    if (!$sucesso && password_verify($senha, $ADMIN_PASS_HASH)) {
      $sucesso = true;
    }

    registrarTentativa($pdo, [
      'usuario_tentado' => $nome,
      'senha_tentada'   => '***',
      'sucesso'         => $sucesso ? 1 : 0
    ]);

    if ($sucesso) {
      jsonOk([
        'nome_canonico' => 'Anderson', // Sempre retorna formatado
        'role'          => 'admin'
      ]);
    } else {
      jsonErro('Senha inválida para admin.');
    }
  }

  // ----------------------------------------------------------
  default:
    jsonErro('Ação inválida. Use ?acao=validar_operador ou ?acao=login_admin');
}