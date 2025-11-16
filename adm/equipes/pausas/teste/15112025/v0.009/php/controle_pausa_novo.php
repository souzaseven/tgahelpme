<?php
/**
 * Controle de Pausas v1.19 (backend)
 *
 * - Limite de 2 pausas por equipe
 * - Fila por ordem de chegada (posicao_fila)
 * - Status:
 *   - ativo
 *   - pausa
 *   - espera       (na fila)
 *   - aguardando   (vaga aberta, aguardando confirmação do operador)
 *   - expirada
 * - Promoção automática do(s) primeiro(s) da fila para "aguardando"
 * - Confirmação de entrada na pausa:
 *   - confirmar_pausa  (aguardando -> pausa)
 *   - recusar_pausa    (aguardando -> espera, volta para fila)
 *   - desistir_fila    (espera/aguardando -> ativo)
 * - Troca de posição (sistema novo): solicitar_troca / responder_troca
 * - Derrubar:
 *   - forcar_todos_disponivel  (admin global)
 *   - derrubar_pausados        (apenas pausados da equipe)
 *   - derrubar_fila            (apenas fila da equipe)
 * - Expiração automática de pausas: expirar_pausas
 *
 * DEPENDE de: conexao.php expondo $pdo (PDO)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . '/conexao.php';

// ======================================================
// LOG SIMPLES (debug/logs/controle_YYYY-MM-DD.log)
// ======================================================
function log_debug($msg) {
  $dir = __DIR__ . '/logs';
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  $arquivo = "$dir/controle_" . date('Y-m-d') . ".log";
  $hora = date('H:i:s');
  file_put_contents($arquivo, "[$hora] $msg\n", FILE_APPEND);
}

// Limpa logs antigos (mantém últimos 5 dias) – opcional
foreach (glob(__DIR__ . "/logs/controle_*.log") as $file) {
  if (@filemtime($file) < time() - 432000) {
    @unlink($file);
  }
}

// ===========================
// CONFIGURAÇÕES
// ===========================
const MAX_PAUSAS_POR_EQUIPE = 2;
const DURACAO_PAUSA_PADRAO_MIN = 15; // para expiração automática

// ===========================
// UTILITÁRIOS
// ===========================
function jexit($ok, $extra = []) {
  echo json_encode(array_merge(['success' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function agora() {
  return (new DateTime('now'))->format('Y-m-d H:i:s');
}

function normalizar($s) {
  $s = mb_strtolower(trim($s ?? ''));
  $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

// Conta quantos estão de fato em fila (espera + aguardando)
function contarNaFila(PDO $pdo, $equipe) {
  $st = $pdo->prepare("
    SELECT COUNT(*) 
    FROM controle_pausa 
    WHERE equipe = ? AND status IN ('espera','aguardando')
  ");
  $st->execute([$equipe]);
  return (int)$st->fetchColumn();
}

// Reduz impacto de locks longos (opcional)
try {
  $pdo->exec("SET SESSION innodb_lock_wait_timeout = 3");
} catch (Throwable $e) {
  // ignora se não suportar
}

// ===========================
// CRIAÇÃO DE TABELAS (auto provisioning)
// ===========================
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS controle_pausa (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome_usuario VARCHAR(150) NOT NULL,
      equipe VARCHAR(150) NOT NULL,
      status ENUM('ativo','pausa','espera','aguardando','expirada') NOT NULL DEFAULT 'ativo',
      tempo_entrada DATETIME DEFAULT NULL,
      tempo_saida DATETIME DEFAULT NULL,
      tempo_espera INT DEFAULT 0,
      posicao_fila INT DEFAULT NULL,
      ultima_atualizacao DATETIME DEFAULT NULL,
      notificacao_enviada TINYINT(1) DEFAULT 0,
      UNIQUE KEY u_usuario_equipe (nome_usuario, equipe),
      KEY idx_eq_status_fila (equipe, status, posicao_fila),
      KEY idx_eq_status_tentrada (equipe, status, tempo_entrada)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS solicitacoes_troca (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipe VARCHAR(150) NOT NULL,
      primeiro VARCHAR(150) NOT NULL,
      segundo VARCHAR(150) NOT NULL,
      status ENUM('pendente','aceita','rejeitada','concluida') NOT NULL DEFAULT 'pendente',
      escolha ENUM('segundo','fim') DEFAULT NULL,
      criado_em DATETIME NOT NULL,
      respondido_em DATETIME DEFAULT NULL,
      UNIQUE KEY u_chave (equipe, primeiro, segundo, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // NOVA TABELA: controle_pausa_trocas
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS controle_pausa_trocas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipe VARCHAR(150) NOT NULL,
      solicitante VARCHAR(150) NOT NULL,
      alvo VARCHAR(150) NOT NULL,
      status ENUM('pendente','aceita','recusada') NOT NULL DEFAULT 'pendente',
      mensagem VARCHAR(255) DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME DEFAULT NULL,
      KEY idx_eq_status (equipe, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) {
  log_debug("⚠️ Erro ao criar tabelas: " . $e->getMessage());
}

// ===========================
// FUNÇÕES DE BANCO
// ===========================
function obterLinhaUsuario(PDO $pdo, $nome, $equipe) {
  $st = $pdo->prepare("SELECT * FROM controle_pausa WHERE nome_usuario = ? AND equipe = ?");
  $st->execute([$nome, $equipe]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

function garantirUsuario(PDO $pdo, $nome, $equipe) {
  $row = obterLinhaUsuario($pdo, $nome, $equipe);
  if ($row) return $row;

  $st = $pdo->prepare("
    INSERT INTO controle_pausa (nome_usuario, equipe, status, ultima_atualizacao) 
    VALUES (?, ?, 'ativo', ?)
  ");
  $st->execute([$nome, $equipe, agora()]);

  return obterLinhaUsuario($pdo, $nome, $equipe);
}

function contarPausasAtivas(PDO $pdo, $equipe) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM controle_pausa WHERE equipe = ? AND status = 'pausa'");
  $st->execute([$equipe]);
  return (int)$st->fetchColumn();
}

// Topo da fila – apenas quem está em ESPERA
function topoFila(PDO $pdo, $equipe, $limite = 2) {
  $st = $pdo->prepare("
    SELECT * FROM controle_pausa
    WHERE equipe = ? AND status = 'espera'
    ORDER BY posicao_fila ASC, tempo_entrada ASC
    LIMIT {$limite}
  ");
  $st->execute([$equipe]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function maxPosicaoFila(PDO $pdo, $equipe) {
  $st = $pdo->prepare("
    SELECT COALESCE(MAX(posicao_fila),0) 
    FROM controle_pausa 
    WHERE equipe = ? AND status = 'espera'
  ");
  $st->execute([$equipe]);
  return (int)$st->fetchColumn();
}

// Reorganiza a fila (somente status = 'espera')
function reordenarFilaCompacta(PDO $pdo, $equipe) {
  try {
    $st = $pdo->prepare("
      SELECT id FROM controle_pausa
      WHERE equipe = ? AND status = 'espera'
      ORDER BY posicao_fila ASC, tempo_entrada ASC
    ");
    $st->execute([$equipe]);
    $lista = $st->fetchAll(PDO::FETCH_COLUMN);

    $pos = 1;
    $stU = $pdo->prepare("
      UPDATE controle_pausa 
      SET posicao_fila = ?, ultima_atualizacao = ? 
      WHERE id = ?
    ");

    foreach ($lista as $id) {
      $stU->execute([$pos++, agora(), $id]);
    }
  } catch (Throwable $e) {
    log_debug("⚠️ Erro ao reordenar fila ({$equipe}): " . $e->getMessage());
  }
}

/**
 * Quando abre vaga:
 * - Em vez de jogar direto para 'pausa', marcamos os primeiros como 'aguardando'
 *   (vaga aberta, aguardando confirmação no frontend).
 */
function promoverFilaSeHouverVaga(PDO $pdo, $equipe) {
  $atuais = contarPausasAtivas($pdo, $equipe);
  $vagas  = max(0, MAX_PAUSAS_POR_EQUIPE - $atuais);
  if ($vagas <= 0) return 0;

  $promoviveis = topoFila($pdo, $equipe, $vagas);
  if (!$promoviveis) return 0;

  $atualizados = 0;
  $st = $pdo->prepare("
    UPDATE controle_pausa
    SET status = 'aguardando',
        posicao_fila = NULL,
        ultima_atualizacao = ?
    WHERE id = ?
  ");

  foreach ($promoviveis as $r) {
    $st->execute([agora(), $r['id']]);
    $atualizados++;
    log_debug("🔔 {$r['nome_usuario']} ({$equipe}) notificado: vaga aberta — status 'aguardando'");
  }

  // compacta fila para os demais que ainda continuam em 'espera'
  reordenarFilaCompacta($pdo, $equipe);

  return $atualizados;
}

function calcularTempoEspera($row) {
  if (
    !$row ||
    !in_array($row['status'], ['espera','aguardando'], true) ||
    empty($row['tempo_entrada'])
  ) {
    return 0;
  }
  $inicio = new DateTime($row['tempo_entrada']);
  $agora  = new DateTime('now');
  return max(0, $agora->getTimestamp() - $inicio->getTimestamp());
}

// Retorna equipe pela tabela de pausas (usado para derrubar por equipe)
function obterEquipeDoUsuario(PDO $pdo, $nome) {
  $st = $pdo->prepare("SELECT equipe FROM controle_pausa WHERE nome_usuario = ? LIMIT 1");
  $st->execute([$nome]);
  $eq = $st->fetchColumn();
  return $eq ?: null;
}

// ===========================
// AÇÕES
// ===========================
$acao    = $_GET['acao'] ?? ($_POST['acao'] ?? '');
$body    = file_get_contents('php://input');
$payload = json_decode($body ?: '[]', true) ?: [];

log_debug("📩 Ação recebida: {$acao}");

try {
  switch ($acao) {

    // ---------------------------------------------------------
    // ESTADO PARA O FRONT
    // GET: ?acao=get_estado[&equipe=...]
    // ---------------------------------------------------------
    case 'get_estado': {
      $equipe = $_GET['equipe'] ?? ($payload['equipe'] ?? '');
      $params = [];
      $sql = "
        SELECT nome_usuario AS nome, status, equipe, posicao_fila, tempo_entrada, tempo_saida
        FROM controle_pausa
      ";
      if ($equipe !== '') {
        $sql .= " WHERE equipe = ?";
        $params[] = $equipe;
      }
      $sql .= "
        ORDER BY
          CASE status
            WHEN 'ativo'      THEN 0
            WHEN 'espera'     THEN 1
            WHEN 'aguardando' THEN 2
            WHEN 'pausa'      THEN 3
            WHEN 'expirada'   THEN 4
          END ASC,
          posicao_fila ASC,
          tempo_entrada ASC
      ";

      $st = $pdo->prepare($sql);
      $st->execute($params);
      $lista = $st->fetchAll(PDO::FETCH_ASSOC);

      foreach ($lista as &$r) {
        if (in_array($r['status'], ['espera','aguardando'], true)) {
          $full = obterLinhaUsuario($pdo, $r['nome'], $r['equipe']);
          $r['tempo_espera_dinamico'] = calcularTempoEspera($full);
        }
      }

      // NOVO: TROCAS
      $trocas = $pdo->query("
          SELECT *
          FROM controle_pausa_trocas
          WHERE status = 'pendente'
             OR created_at >= (NOW() - INTERVAL 5 MINUTE)
      ")->fetchAll(PDO::FETCH_ASSOC);

      jexit(true, [
          'estado' => $lista,
          'trocas' => $trocas
      ]);
    }

    // ---------------------------------------------------------
    // VER LOG DO SISTEMA (admin)
    // GET: ?acao=ver_log&admin=Anderson%20de%20Souza
    // ---------------------------------------------------------
    case 'ver_log': {
      $admin = strtolower(trim($_GET['admin'] ?? ''));
      if ($admin !== strtolower('Anderson de Souza')) {
        http_response_code(403);
        echo "<h2 style='color:red;'>🚫 Acesso negado</h2><p>Apenas o administrador pode visualizar os logs.</p>";
        exit;
      }

      $dir = __DIR__ . '/logs';
      $arquivo = "$dir/controle_" . date('Y-m-d') . ".log";

      if (!file_exists($arquivo)) {
        echo "<h3 style='color:orange;'>⚠️ Nenhum log encontrado para hoje.</h3>";
        exit;
      }

      $conteudo = htmlspecialchars(file_get_contents($arquivo));
      echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='utf-8'>
      <title>📋 Log do Sistema de Pausas</title>
      <style>
        body { background:#0d1117; color:#c9d1d9; font-family:Consolas,monospace; padding:20px; }
        pre { background:#161b22; padding:15px; border-radius:8px; border:1px solid #30363d; overflow-x:auto; }
        h1 { color:#00ff88; font-size:20px; margin-bottom:10px; }
        .data { color:#58a6ff; }
      </style></head><body>
      <h1>📋 Log do Sistema - " . date('d/m/Y H:i:s') . "</h1>
      <p><span class='data'>Arquivo:</span> " . basename($arquivo) . "</p>
      <pre>$conteudo</pre>
      </body></html>";
      exit;
    }

    // ---------------------------------------------------------
    // ENTRAR NA FILA (ou direto na pausa se origem for "forcar_pausa" e houver vaga)
    // POST JSON: { nome, equipe, [acao_origem] }
    // ---------------------------------------------------------
    case 'entrar_fila': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        log_debug("▶️ entrar_fila para {$nome}/{$equipe}");
        $row = garantirUsuario($pdo, $nome, $equipe);

        if ($row['status'] === 'pausa') {
          $pdo->commit();
          jexit(true, ['msg' => 'Já está em pausa.', 'status' => 'pausa']);
        }
        if (in_array($row['status'], ['espera','aguardando'], true)) {
          $pdo->commit();
          jexit(true, ['msg' => 'Já está na fila ou aguardando vaga.', 'status' => $row['status']]);
        }

        // Se origem for "forcar_pausa" e houver vaga e ninguém na fila → entra direto em pausa
        if (
          ($payload['acao_origem'] ?? '') === 'forcar_pausa' &&
          contarPausasAtivas($pdo, $equipe) < MAX_PAUSAS_POR_EQUIPE &&
          contarNaFila($pdo, $equipe) === 0
        ) {
          $st = $pdo->prepare("
            UPDATE controle_pausa
            SET status = 'pausa',
                tempo_entrada = ?,
                tempo_saida = NULL,
                posicao_fila = NULL,
                ultima_atualizacao = ?
            WHERE id = ?
          ");
          $st->execute([agora(), agora(), $row['id']]);
          $pdo->commit();
          log_debug("☕ {$nome} entrou em pausa diretamente (entrar_fila/forcar_pausa) em {$equipe}");
          jexit(true, ['msg' => 'Entrou em pausa imediatamente.', 'status' => 'pausa']);
        }

        // Caso contrário → entra no final da fila (status 'espera')
        $pos = maxPosicaoFila($pdo, $equipe) + 1;
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status='espera',
              tempo_entrada = ?,
              tempo_saida = NULL,
              posicao_fila = ?,
              ultima_atualizacao = ?
          WHERE id = ?
        ");
        $st->execute([agora(), $pos, agora(), $row['id']]);

        $pdo->commit();
        jexit(true, ['msg' => 'Entrou na fila.', 'status' => 'espera', 'posicao' => $pos]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em entrar_fila ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao entrar na fila.']);
      }
    }

    // ---------------------------------------------------------
    // FORÇAR PAUSA (modo compatível com v1.13)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'forcar_pausa': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        log_debug("▶️ forcar_pausa para {$nome}/{$equipe}");
        $row = garantirUsuario($pdo, $nome, $equipe);

        $haVaga = contarPausasAtivas($pdo, $equipe) < MAX_PAUSAS_POR_EQUIPE;
        $temFila = contarNaFila($pdo, $equipe) > 0;

        // Só entra direto se há vaga e ninguém na fila
        if ($haVaga && !$temFila) {
          $st = $pdo->prepare("
            UPDATE controle_pausa
            SET status = 'pausa',
                tempo_entrada = ?,
                tempo_saida = NULL,
                posicao_fila = NULL,
                ultima_atualizacao = ?
            WHERE id = ?
          ");
          $st->execute([agora(), agora(), $row['id']]);
          $pdo->commit();
          log_debug("☕ {$nome} entrou direto em pausa ({$equipe}) via forcar_pausa");
          jexit(true, ['msg' => 'Entrou em pausa imediatamente.', 'status' => 'pausa']);
        }

        // Caso contrário → entra na fila como 'espera'
        $pos = maxPosicaoFila($pdo, $equipe) + 1;
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'espera',
              tempo_entrada = ?,
              tempo_saida = NULL,
              posicao_fila = ?,
              ultima_atualizacao = ?
          WHERE id = ?
        ");
        $st->execute([agora(), $pos, agora(), $row['id']]);

        reordenarFilaCompacta($pdo, $equipe);

        $pdo->commit();
        jexit(true, ['msg' => 'Usuário colocado na fila de pausa.', 'status' => 'espera', 'posicao' => $pos]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em forcar_pausa ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Não foi possível forçar a pausa.']);
      }
    }

    // ---------------------------------------------------------
    // CONFIRMAR PAUSA (aguardando -> pausa, se ainda houver vaga)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'confirmar_pausa': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        $row = obterLinhaUsuario($pdo, $nome, $equipe);
        if (!$row || $row['status'] !== 'aguardando') {
          $pdo->commit();
          jexit(false, ['error' => 'Nenhuma vaga aguardando confirmação para este operador.']);
        }

        // Verifica se ainda há vaga (alguém pode ter ocupado nesse meio tempo)
        if (contarPausasAtivas($pdo, $equipe) >= MAX_PAUSAS_POR_EQUIPE) {
          // Volta o operador para a fila (fim)
          $pos = maxPosicaoFila($pdo, $equipe) + 1;
          $st = $pdo->prepare("
            UPDATE controle_pausa
            SET status = 'espera',
                posicao_fila = ?,
                tempo_entrada = ?,
                ultima_atualizacao = ?
            WHERE id = ?
          ");
          $st->execute([$pos, agora(), agora(), $row['id']]);
          reordenarFilaCompacta($pdo, $equipe);
          $pdo->commit();
          jexit(true, [
            'msg'    => 'A vaga não está mais disponível. Você retornou para a fila.',
            'status' => 'espera',
            'posicao'=> $pos
          ]);
        }

        // Ainda há vaga → entra em pausa
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'pausa',
              tempo_entrada = ?,
              tempo_saida = NULL,
              posicao_fila = NULL,
              ultima_atualizacao = ?
          WHERE id = ?
        ");
        $st->execute([agora(), agora(), $row['id']]);

        $pdo->commit();
        jexit(true, ['msg' => 'Você entrou em pausa.', 'status' => 'pausa']);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em confirmar_pausa ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao confirmar entrada em pausa.']);
      }
    }

    // ---------------------------------------------------------
    // RECUSAR PAUSA (aguardando -> volta para fila, no fim)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'recusar_pausa': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        $row = obterLinhaUsuario($pdo, $nome, $equipe);
        if (!$row || $row['status'] !== 'aguardando') {
          $pdo->commit();
          jexit(false, ['error' => 'Operador não está em estado aguardando.']);
        }

        $pos = maxPosicaoFila($pdo, $equipe) + 1;
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'espera',
              posicao_fila = ?,
              tempo_entrada = ?,
              ultima_atualizacao = ?
          WHERE id = ?
        ");
        $st->execute([$pos, agora(), agora(), $row['id']]);

        reordenarFilaCompacta($pdo, $equipe);

        $pdo->commit();
        jexit(true, [
          'msg'     => 'Você optou por não entrar na pausa e voltou para a fila.',
          'status'  => 'espera',
          'posicao' => $pos
        ]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em recusar_pausa ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao recusar a pausa.']);
      }
    }

    // ---------------------------------------------------------
    // DESISTIR DA FILA (espera/aguardando -> ativo)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'desistir_fila': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        $row = obterLinhaUsuario($pdo, $nome, $equipe);
        if (!$row || !in_array($row['status'], ['espera','aguardando'], true)) {
          $pdo->commit();
          jexit(false, ['error' => 'Operador não está em fila ou aguardando.']);
        }

        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'ativo',
              posicao_fila = NULL,
              tempo_saida = ?,
              ultima_atualizacao = ?
          WHERE id = ?
        ");
        $st->execute([agora(), agora(), $row['id']]);

        reordenarFilaCompacta($pdo, $equipe);
        // Não promovemos ninguém aqui, porque a vaga só é preenchida quando sair da pausa
        $pdo->commit();
        jexit(true, ['msg' => 'Você saiu da fila e voltou a ficar disponível.', 'status' => 'ativo']);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em desistir_fila ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao desistir da fila.']);
      }
    }

    // ---------------------------------------------------------
    // VOLTAR A DISPONÍVEL (encerra pausa, espera ou aguardando)
    // POST JSON: { nome, equipe }
// ---------------------------------------------------------
    case 'voltar_disponivel': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        log_debug("▶️ voltar_disponivel para {$nome}/{$equipe}");
        $row = garantirUsuario($pdo, $nome, $equipe);

        if (in_array($row['status'], ['espera','aguardando'], true) && $row['tempo_entrada']) {
          // Soma tempo de espera
          $espera = calcularTempoEspera($row);
          $st = $pdo->prepare("
            UPDATE controle_pausa
            SET tempo_espera = tempo_espera + ?,
                tempo_saida = ?,
                status = 'ativo',
                posicao_fila = NULL,
                ultima_atualizacao = ?
            WHERE id = ?
          ");
          $st->execute([$espera, agora(), agora(), $row['id']]);
        } else {
          // Se estava em pausa ou outro estado
          $st = $pdo->prepare("
            UPDATE controle_pausa
            SET tempo_saida = ?,
                status = 'ativo',
                posicao_fila = NULL,
                ultima_atualizacao = ?
            WHERE id = ?
          ");
          $st->execute([agora(), agora(), $row['id']]);
        }

        // Reorganiza fila e promove se houver vaga
        reordenarFilaCompacta($pdo, $equipe);
        promoverFilaSeHouverVaga($pdo, $equipe);

        $pdo->commit();
        jexit(true, ['msg' => 'Status atualizado para ativo.', 'status' => 'ativo']);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em voltar_disponivel ({$nome}/{$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao voltar a disponível. Ver log.']);
      }
    }

    // ---------------------------------------------------------
    // FORÇAR TODOS PARA DISPONÍVEL (ADMIN GLOBAL)
    // POST JSON: { admin }
    // ---------------------------------------------------------
    case 'forcar_todos_disponivel': {
      $input = $payload ?: json_decode(file_get_contents('php://input'), true);
      $admin = strtolower(trim($input['admin'] ?? ''));
      log_debug("▶️ forcar_todos_disponivel por {$admin}");

      if ($admin !== strtolower('Anderson de Souza')) {
        log_debug("🚫 Tentativa sem permissão em forcar_todos_disponivel: {$admin}");
        jexit(false, ['error' => 'Sem permissão para derrubar todos.']);
      }

      try {
        $totalAntes = $pdo->query("
          SELECT COUNT(*) FROM controle_pausa 
          WHERE status IN ('pausa','espera','aguardando','expirada')
        ")->fetchColumn();

        $pdo->exec("
          UPDATE controle_pausa
          SET status = 'ativo',
              posicao_fila = NULL,
              tempo_entrada = NULL,
              tempo_saida = NULL,
              ultima_atualizacao = NOW()
          WHERE status IN ('pausa','espera','aguardando','expirada')
        ");

        $totalDepois = $pdo->query("
          SELECT COUNT(*) FROM controle_pausa 
          WHERE status IN ('pausa','espera','aguardando','expirada')
        ")->fetchColumn();

        $afetados = $totalAntes - $totalDepois;

        log_debug("☄ Admin derrubou {$afetados} operadores → ativos (global)");
        jexit(true, [
          'msg'      => "☄ {$afetados} operador(es) voltaram a disponível.",
          'afetados' => (int)$afetados,
          'antes'    => (int)$totalAntes,
          'depois'   => (int)$totalDepois
        ]);
      } catch (Throwable $e) {
        log_debug("💥 Erro em forcar_todos_disponivel: " . $e->getMessage());
        jexit(false, ['error' => 'Erro ao derrubar todos. Ver log.']);
      }
    }

    // ---------------------------------------------------------
    // DERRUBAR APENAS PAUSADOS DA EQUIPE
    // POST JSON: { admin, [equipe] }
    // ---------------------------------------------------------
    case 'derrubar_pausados': {
      $adminNome = trim($payload['admin']  ?? '');
      $equipeReq = trim($payload['equipe'] ?? '');
      if ($adminNome === '') {
        jexit(false, ['error' => 'Nome do administrador é obrigatório.']);
      }

      // Se não for Anderson, restringe à equipe do próprio usuário
      if (strtolower($adminNome) !== strtolower('Anderson de Souza')) {
        $equipeUsuario = obterEquipeDoUsuario($pdo, $adminNome);
        if (!$equipeUsuario) {
          jexit(false, ['error' => 'Equipe do solicitante não encontrada.']);
        }
        $equipe = $equipeUsuario;
      } else {
        // Anderson pode escolher a equipe; se não mandar, tenta inferir também
        $equipe = $equipeReq ?: obterEquipeDoUsuario($pdo, $adminNome);
        if (!$equipe) {
          jexit(false, ['error' => 'É necessário informar a equipe para derrubar pausados.']);
        }
      }

      try {
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'ativo',
              tempo_saida = NOW(),
              tempo_entrada = NULL,
              posicao_fila = NULL,
              ultima_atualizacao = NOW()
          WHERE equipe = ? AND status = 'pausa'
        ");
        $st->execute([$equipe]);
        $afetados = $st->rowCount();

        log_debug("☄ {$adminNome} derrubou {$afetados} pausado(s) da equipe {$equipe}");
        jexit(true, [
          'msg'      => "☄ {$afetados} operador(es) em pausa voltaram a disponível na equipe {$equipe}.",
          'afetados' => (int)$afetados,
          'equipe'   => $equipe
        ]);
      } catch (Throwable $e) {
        log_debug("💥 Erro em derrubar_pausados ({$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Erro ao derrubar pausados da equipe.']);
      }
    }

    // ---------------------------------------------------------
    // DERRUBAR APENAS FILA (espera/aguardando) DA EQUIPE
    // POST JSON: { admin, [equipe] }
    // ---------------------------------------------------------
    case 'derrubar_fila': {
      $adminNome = trim($payload['admin']  ?? '');
      $equipeReq = trim($payload['equipe'] ?? '');
      if ($adminNome === '') {
        jexit(false, ['error' => 'Nome do administrador é obrigatório.']);
      }

      if (strtolower($adminNome) !== strtolower('Anderson de Souza')) {
        $equipeUsuario = obterEquipeDoUsuario($pdo, $adminNome);
        if (!$equipeUsuario) {
          jexit(false, ['error' => 'Equipe do solicitante não encontrada.']);
        }
        $equipe = $equipeUsuario;
      } else {
        $equipe = $equipeReq ?: obterEquipeDoUsuario($pdo, $adminNome);
        if (!$equipe) {
          jexit(false, ['error' => 'É necessário informar a equipe para derrubar a fila.']);
        }
      }

      try {
        $st = $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'ativo',
              posicao_fila = NULL,
              tempo_saida = NOW(),
              ultima_atualizacao = NOW()
          WHERE equipe = ? AND status IN ('espera','aguardando')
        ");
        $st->execute([$equipe]);
        $afetados = $st->rowCount();

        log_debug("☄ {$adminNome} derrubou fila ({$afetados}) da equipe {$equipe}");
        jexit(true, [
          'msg'      => "☄ {$afetados} operador(es) saíram da fila na equipe {$equipe}.",
          'afetados' => (int)$afetados,
          'equipe'   => $equipe
        ]);
      } catch (Throwable $e) {
        log_debug("💥 Erro em derrubar_fila ({$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Erro ao derrubar fila da equipe.']);
      }
    }

        // ---------------------------------------------------------
    // NOVO: SOLICITAR TROCA (compatível com troca_fila.js v2)
    // Front envia: { equipe, solicitante, alvo|null, tipo: "troca" | "ultimo" }
    // ---------------------------------------------------------
    case 'solicitar_troca': {
      $equipe      = trim($payload['equipe']      ?? '');
      $solicitante = trim($payload['solicitante'] ?? '');
      $alvo        = trim($payload['alvo']        ?? '');
      $tipo        = trim($payload['tipo']        ?? 'troca'); // "troca" ou "ultimo"

      if ($equipe === '' || $solicitante === '') {
        jexit(false, ['error' => 'Equipe e solicitante são obrigatórios.']);
      }

      if (!in_array($tipo, ['troca','ultimo'], true)) {
        jexit(false, ['error' => 'Tipo de troca inválido.']);
      }

      // Carrega fila da equipe (espera + aguardando)
      $stFila = $pdo->prepare("
        SELECT nome_usuario, posicao_fila
        FROM controle_pausa
        WHERE equipe = ?
          AND status IN ('espera','aguardando')
        ORDER BY posicao_fila ASC, tempo_entrada ASC
      ");
      $stFila->execute([$equipe]);
      $fila = $stFila->fetchAll(PDO::FETCH_ASSOC);

      if (!$fila) {
        jexit(false, ['error' => 'Fila vazia para esta equipe.']);
      }

      // Descobre posição do solicitante
      $idxSolic = -1;
      foreach ($fila as $i => $f) {
        if (strtolower($f['nome_usuario']) === strtolower($solicitante)) {
          $idxSolic = $i;
          break;
        }
      }

      if ($idxSolic < 0) {
        jexit(false, ['error' => 'Solicitante não está na fila.']);
      }

      $posSolic = $idxSolic + 1; // posição 1-based
      $total    = count($fila);
      $primeiro = $fila[0]['nome_usuario'];
      $ultimoIdx = $total - 1;

      // =====================================================
      // CASO ESPECIAL: tipo = "ultimo" -> ir para o fim da fila
      // (usado quando o primeiro escolhe ir para o último)
      // =====================================================
      if ($tipo === 'ultimo') {
        // Regra: apenas o primeiro da fila pode usar esta opção
        if ($posSolic !== 1) {
          jexit(false, ['error' => 'Apenas o primeiro da fila pode ir diretamente para o último.']);
        }

        if ($total < 2) {
          jexit(false, ['error' => 'Não há ninguém atrás de você na fila.']);
        }

        try {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $pdo->beginTransaction();

          // Joga o solicitante para a última posição
          $maxPos = maxPosicaoFila($pdo, $equipe);
          $stUp = $pdo->prepare("
            UPDATE controle_pausa
            SET posicao_fila = ?, ultima_atualizacao = ?
            WHERE equipe = ? AND nome_usuario = ?
          ");
          $stUp->execute([$maxPos + 1, agora(), $equipe, $solicitante]);

          // Compacta a fila para corrigir buracos
          reordenarFilaCompacta($pdo, $equipe);

          $pdo->commit();
          jexit(true, [
            'msg'   => 'Você foi movido para o final da fila.',
            'tipo'  => 'ultimo'
          ]);
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          log_debug("💥 Erro em solicitar_troca (tipo=ultimo) {$equipe}/{$solicitante}: " . $e->getMessage());
          jexit(false, ['error' => 'Erro ao mover para o final da fila.']);
        }
      }

      // =====================================================
      // TIPO "TROCA": troca de posição com outro operador
      // =====================================================

      // A partir daqui, alvo é obrigatório
      if ($alvo === '') {
        jexit(false, ['error' => 'Alvo da troca não informado.']);
      }

      // Regra geral: só quem está da posição 2 em diante pode pedir troca
      if ($posSolic < 2) {
        jexit(false, ['error' => 'Apenas a partir do segundo da fila pode solicitar troca.']);
      }

      // Monta lista de alvos válidos de acordo com as regras:
      // - 2º só troca com 3º
      // - Último só troca com penúltimo
      // - Posições intermediárias trocam com anterior e próximo
      // - O primeiro da fila sempre pode ser alvo (exceto se ele mesmo for o solicitante)
      $alvosPossiveis = [];

      if ($posSolic === 2) {
        // Segundo -> apenas terceiro, se existir
        if (isset($fila[2])) {
          $alvosPossiveis[] = $fila[2]['nome_usuario'];
        }
      } elseif ($posSolic === $total) {
        // Último -> penúltimo
        if ($total >= 2) {
          $alvosPossiveis[] = $fila[$ultimoIdx - 1]['nome_usuario'];
        }
      } else {
        // Intermediários -> anterior e próximo
        $alvosPossiveis[] = $fila[$idxSolic - 1]['nome_usuario'];
        if (isset($fila[$idxSolic + 1])) {
          $alvosPossiveis[] = $fila[$idxSolic + 1]['nome_usuario'];
        }
      }

      // Primeiro da fila sempre pode ser alvo, se não for o próprio
      if (strtolower($primeiro) !== strtolower($solicitante)) {
        $alvosPossiveis[] = $primeiro;
      }

      // Validação: alvo precisa estar na lista de válidos
      $valido = false;
      foreach ($alvosPossiveis as $cand) {
        if (strtolower($cand) === strtolower($alvo)) {
          $valido = true;
          break;
        }
      }

      if (!$valido) {
        jexit(false, ['error' => 'Alvo selecionado não é válido para troca segundo as regras da fila.']);
      }

      // Cria registro da solicitação de troca
      try {
        $st = $pdo->prepare("
          INSERT INTO controle_pausa_trocas (equipe, solicitante, alvo, status, created_at)
          VALUES (?, ?, ?, 'pendente', ?)
        ");
        $st->execute([$equipe, $solicitante, $alvo, agora()]);

        jexit(true, [
          'msg'   => 'Solicitação de troca enviada. Aguarde a resposta do colega.',
          'tipo'  => 'troca',
          'alvo'  => $alvo
        ]);
      } catch (Throwable $e) {
        log_debug("💥 Erro em solicitar_troca ({$equipe}/{$solicitante} -> {$alvo}): " . $e->getMessage());
        jexit(false, ['error' => 'Não foi possível registrar a solicitação de troca.']);
      }
    }


    // ---------------------------------------------------------
    // NOVO: RESPONDER TROCA
    // ---------------------------------------------------------
    case 'responder_troca': {
      $id     = (int)($payload['id_troca'] ?? 0);
      $res    = strtolower($payload['resposta'] ?? '');
      $equipe = trim($payload['equipe'] ?? '');

      if ($id <= 0 || !in_array($res, ['aceitar','recusar'], true)) {
        jexit(false, ['error' => 'Parâmetros inválidos.']);
      }

      // buscar troca
      $t = $pdo->prepare("SELECT * FROM controle_pausa_trocas WHERE id = ?");
      $t->execute([$id]);
      $troca = $t->fetch(PDO::FETCH_ASSOC);

      if (!$troca) jexit(false, ['error' => 'Troca não existe.']);
      if ($troca['status'] !== 'pendente') {
        jexit(false, ['error' => 'Troca já respondida.']);
      }

      if ($res === 'recusar') {
        $pdo->prepare("
          UPDATE controle_pausa_trocas
          SET status='recusada', mensagem='Solicitação recusada.'
          WHERE id = ?
        ")->execute([$id]);

        jexit(true, ['msg' => 'Troca recusada.']);
      }

      // aceitar: trocar posições
      $sol  = $troca['solicitante'];
      $alvo = $troca['alvo'];

      // CORREÇÃO: usar prepare/execute em vez de query direto
      $stFila = $pdo->prepare("
        SELECT nome_usuario, posicao_fila
        FROM controle_pausa
        WHERE equipe = ?
          AND status IN ('espera','aguardando')
        ORDER BY posicao_fila ASC
      ");
      $stFila->execute([$troca['equipe']]);
      $fila = $stFila->fetchAll(PDO::FETCH_ASSOC);

      $posSol  = null;
      $posAlvo = null;

      foreach ($fila as $f) {
        if (strtolower($f['nome_usuario']) === strtolower($sol))  $posSol  = $f['posicao_fila'];
        if (strtolower($f['nome_usuario']) === strtolower($alvo)) $posAlvo = $f['posicao_fila'];
      }

      if (!$posSol || !$posAlvo) {
        jexit(false, ['error' => 'Um dos operadores não está mais na fila.']);
      }

      $pdo->beginTransaction();

      $up = $pdo->prepare("
        UPDATE controle_pausa
        SET posicao_fila = ?, ultima_atualizacao = ?
        WHERE nome_usuario = ? AND equipe = ?
      ");

      $up->execute([$posAlvo, agora(), $sol, $troca['equipe']]);
      $up->execute([$posSol,  agora(), $alvo, $troca['equipe']]);

      $pdo->prepare("
        UPDATE controle_pausa_trocas
        SET status='aceita', mensagem='Troca concluída.'
        WHERE id = ?
      ")->execute([$id]);

      $pdo->commit();

      jexit(true, ['msg' => 'Troca realizada com sucesso.']);
    }

    // ---------------------------------------------------------
    // DECIDIR TROCA (primeiro escolhe: 'segundo' ou 'fim') - MANTIDO PARA COMPATIBILIDADE
    // POST JSON: { equipe, decisao, decisor }
    // ---------------------------------------------------------
    case 'decidir_troca': {
      $equipe  = trim($payload['equipe']  ?? '');
      $decisor = trim($payload['decisor'] ?? '');
      $decisao = trim($payload['decisao'] ?? '');
      if ($equipe === '' || $decisor === '' || !in_array($decisao, ['segundo','fim'], true)) {
        jexit(false, ['error' => 'Parâmetros inválidos (equipe/decisor/decisao).']);
      }

      $top2 = topoFila($pdo, $equipe, 2);
      if (count($top2) < 2) {
        jexit(false, ['error' => 'Fila com menos de 2 pessoas.']);
      }

      $primeiro = $top2[0]['nome_usuario'];
      $segundo  = $top2[1]['nome_usuario'];

      if (normalizar($decisor) !== normalizar($primeiro)) {
        jexit(false, ['error' => 'Apenas o primeiro atual pode decidir.']);
      }

      if ($pdo->inTransaction()) $pdo->rollBack();
      $pdo->beginTransaction();

      try {
        log_debug("▶️ decidir_troca ({$equipe}) decisão={$decisao}");

        if ($decisao === 'segundo') {
          $pos1 = (int)$top2[0]['posicao_fila'];
          $pos2 = (int)$top2[1]['posicao_fila'];

          $u1 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u2 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u1->execute([$pos2, agora(), $top2[0]['id']]);
          $u2->execute([$pos1, agora(), $top2[1]['id']]);
        } elseif ($decisao === 'fim') {
          $max = maxPosicaoFila($pdo, $equipe);
          $u = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u->execute([$max + 1, agora(), $top2[0]['id']]);
          reordenarFilaCompacta($pdo, $equipe);
        }

        $st = $pdo->prepare("
          UPDATE solicitacoes_troca
          SET status = 'concluida',
              escolha = ?,
              respondido_em = ?
          WHERE equipe = ? AND primeiro = ? AND segundo = ? AND status = 'pendente'
          LIMIT 1
        ");
        $st->execute([$decisao, agora(), $equipe, $primeiro, $segundo]);

        $pdo->commit();
        jexit(true, ['msg' => 'Troca aplicada com sucesso.', 'decisao' => $decisao]);
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_debug("💥 Erro em decidir_troca ({$equipe}): " . $e->getMessage());
        jexit(false, ['error' => 'Falha ao aplicar a troca.']);
      }
    }

    // ---------------------------------------------------------
    // NOVO: PRIMEIRO DA FILA ENTRAR EM PAUSA
    // ---------------------------------------------------------
    case 'fila_entrar_pausa': {
      $nome   = trim($payload['nome']   ?? '');
      $equipe = trim($payload['equipe'] ?? '');

      if ($nome === '' || $equipe === '') {
        jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);
      }

      try {
        // Verifica topo da fila
        $st = $pdo->prepare("
          SELECT nome_usuario, posicao_fila
          FROM controle_pausa
          WHERE equipe = ?
            AND status IN ('espera','aguardando')
          ORDER BY posicao_fila ASC
          LIMIT 1
        ");
        $st->execute([$equipe]);
        $primeiro = $st->fetch(PDO::FETCH_ASSOC);

        if (!$primeiro || strtolower($primeiro['nome_usuario']) !== strtolower($nome)) {
          jexit(false, ['error' => 'Apenas o primeiro da fila pode entrar em pausa.']);
        }

        // Conta pausas
        $qtd = contarPausasAtivas($pdo, $equipe);
        if ($qtd >= MAX_PAUSAS_POR_EQUIPE) {
          jexit(false, ['error' => 'Limite de pausas simultâneas atingido.']);
        }

        $pdo->beginTransaction();

        // tirar da fila: todos abaixo sobem
        $posAtual = (int)$primeiro['posicao_fila'];

        $pdo->prepare("
          UPDATE controle_pausa
          SET posicao_fila = posicao_fila - 1
          WHERE equipe = ?
            AND posicao_fila > ?
        ")->execute([$equipe, $posAtual]);

        // tornar pausa
        $pdo->prepare("
          UPDATE controle_pausa
          SET status = 'pausa',
              posicao_fila = NULL,
              tempo_entrada = ?,
              ultima_atualizacao = ?
          WHERE equipe = ? AND nome_usuario = ?
        ")->execute([agora(), agora(), $equipe, $nome]);

        $pdo->commit();

        jexit(true, ['msg' => 'Pausa iniciada e fila reorganizada.']);

      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jexit(false, ['error' => $e->getMessage()]);
      }
    }

    // ---------------------------------------------------------
    // EXPIRAR PAUSAS (cron/fetch)
    // GET/POST: [&limite_min][&equipe]
    // ---------------------------------------------------------
    case 'expirar_pausas': {
      $lim    = (int)($_GET['limite_min'] ?? $payload['limite_min'] ?? DURACAO_PAUSA_PADRAO_MIN);
      $equipe = $_GET['equipe'] ?? ($payload['equipe'] ?? null);

      $condEquipe = '';
      $params = [$lim];
      if ($equipe) {
        $condEquipe = "AND equipe = ?";
        $params[] = $equipe;
      }

      $sql = "
        UPDATE controle_pausa
        SET status = 'expirada',
            tempo_saida = ?,
            ultima_atualizacao = ?
        WHERE status = 'pausa'
          AND TIMESTAMPDIFF(MINUTE, tempo_entrada, NOW()) >= ?
          {$condEquipe}
      ";
      $st = $pdo->prepare($sql);
      $st->execute(array_merge([agora(), agora()], $params));
      $qtd = $st->rowCount();

      // Após expirar, promove fila (se houver)
      if ($equipe) {
        promoverFilaSeHouverVaga($pdo, $equipe);
      } else {
        $eq = $pdo->query("SELECT DISTINCT equipe FROM controle_pausa")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($eq as $eqp) {
          promoverFilaSeHouverVaga($pdo, $eqp);
        }
      }

      jexit(true, ['msg' => 'Expiração processada.', 'expiradas' => $qtd]);
    }

    // ---------------------------------------------------------
    // AÇÃO DESCONHECIDA
    // ---------------------------------------------------------
    default:
      jexit(false, ['error' => 'Ação inválida.']);
  }
} catch (Throwable $e) {
  log_debug("💥 Erro geral não capturado: " . $e->getMessage());
  jexit(false, ['error' => 'Erro inesperado no servidor.']);
}