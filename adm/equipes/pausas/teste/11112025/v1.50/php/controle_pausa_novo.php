<?php

/**
 * Controle de Pausas v1.14 (backend)
 * - Limite de 2 pausas por equipe
 * - Fila por ordem de chegada (posicao_fila)
 * - Promoção automática do(s) primeiro(s) da fila quando abrir vaga
 * - Troca entre 1º e 2º da fila (aceita e escolhe ficar como segundo ou ir ao fim)
 * - Contador de espera (tempo_espera) e tempos de entrada/saída
 * - Ações compatíveis com v1.13: get_estado, entrar_fila, forcar_pausa, voltar_disponivel
 * - Novas ações: solicitar_troca, decidir_troca, expirar_pausas
 *
 * DEPENDE de: php/conexao.php com $pdo (PDO) já conectado
 */



header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

require_once __DIR__ . '/conexao.php'; // expõe $pdo

// ===========================
// CONFIGURAÇÕES
// ===========================
const MAX_PAUSAS_POR_EQUIPE = 2;
// Opcional: duração padrão de pausa para expiração automática
const DURACAO_PAUSA_PADRAO_MIN = 15;

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

// ===========================
// CRIAÇÃO DE TABELAS (opcional - auto provisioning)
// Comente se já existirem.
// ===========================
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS controle_pausa (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome_usuario VARCHAR(150) NOT NULL,
      equipe VARCHAR(150) NOT NULL,
      status ENUM('ativo','pausa','espera','expirada') NOT NULL DEFAULT 'ativo',
      tempo_entrada DATETIME DEFAULT NULL,
      tempo_saida DATETIME DEFAULT NULL,
      tempo_espera INT DEFAULT 0,
      posicao_fila INT DEFAULT NULL,
      ultima_atualizacao DATETIME DEFAULT NULL,
      notificacao_enviada TINYINT(1) DEFAULT 0,
      UNIQUE KEY u_usuario_equipe (nome_usuario, equipe)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS solicitacoes_troca (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipe VARCHAR(150) NOT NULL,
      primeiro VARCHAR(150) NOT NULL,     -- quem está em 1º na fila
      segundo VARCHAR(150) NOT NULL,      -- quem está em 2º na fila (solicitante)
      status ENUM('pendente','aceita','rejeitada','concluida') NOT NULL DEFAULT 'pendente',
      escolha ENUM('segundo','fim') DEFAULT NULL, -- decisão do primeiro
      criado_em DATETIME NOT NULL,
      respondido_em DATETIME DEFAULT NULL,
      UNIQUE KEY u_chave (equipe, primeiro, segundo, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} catch (Throwable $e) {
  // Não bloqueia; apenas registra no log do servidor se necessário
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
  $st = $pdo->prepare("INSERT INTO controle_pausa (nome_usuario, equipe, status, ultima_atualizacao) VALUES (?, ?, 'ativo', ?)");
  $st->execute([$nome, $equipe, agora()]);
  return obterLinhaUsuario($pdo, $nome, $equipe);
}

function contarPausasAtivas(PDO $pdo, $equipe) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM controle_pausa WHERE equipe = ? AND status = 'pausa'");
  $st->execute([$equipe]);
  return (int)$st->fetchColumn();
}

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
  $st = $pdo->prepare("SELECT COALESCE(MAX(posicao_fila),0) FROM controle_pausa WHERE equipe = ? AND status = 'espera'");
  $st->execute([$equipe]);
  return (int)$st->fetchColumn();
}

function reordenarFilaCompacta(PDO $pdo, $equipe) {
  // remove buracos nas posições (1..n)
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("
      SELECT id FROM controle_pausa
      WHERE equipe = ? AND status = 'espera'
      ORDER BY posicao_fila ASC, tempo_entrada ASC
    ");
    $st->execute([$equipe]);
    $lista = $st->fetchAll(PDO::FETCH_COLUMN);

    $pos = 1;
    $stU = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
    foreach ($lista as $id) {
      $stU->execute([$pos, agora(), $id]);
      $pos++;
    }
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
  }
}

function promoverFilaSeHouverVaga(PDO $pdo, $equipe) {
  $atuais = contarPausasAtivas($pdo, $equipe);
  $vagas = max(0, MAX_PAUSAS_POR_EQUIPE - $atuais);
  if ($vagas <= 0) return 0;

  $promoviveis = topoFila($pdo, $equipe, $vagas);
  if (!$promoviveis) return 0;

  $atualizados = 0;
  $st = $pdo->prepare("
    UPDATE controle_pausa
    SET status = 'pausa', tempo_saida = NULL, tempo_entrada = ?, posicao_fila = NULL, ultima_atualizacao = ?
    WHERE id = ?
  ");
  foreach ($promoviveis as $r) {
    $st->execute([agora(), agora(), $r['id']]);
    $atualizados++;
  }

  // compacta fila
  reordenarFilaCompacta($pdo, $equipe);

  return $atualizados;
}

function calcularTempoEspera($row) {
  if (!$row || $row['status'] !== 'espera' || empty($row['tempo_entrada'])) return 0;
  $inicio = new DateTime($row['tempo_entrada']);
  $agora  = new DateTime('now');
  return max(0, $agora->getTimestamp() - $inicio->getTimestamp());
}

// ===========================
// AÇÕES
// ===========================
$acao = $_GET['acao'] ?? ($_POST['acao'] ?? '');
$body = file_get_contents('php://input');
$payload = json_decode($body ?: '[]', true) ?: [];

try {
  switch ($acao) {

    // ---------------------------------------------------------
    // ESTADO PARA O FRONT (compatível v1.13)
    // GET: ?acao=get_estado[&equipe=...]
    // ---------------------------------------------------------
    case 'get_estado': {
      $equipe = $_GET['equipe'] ?? ($payload['equipe'] ?? '');
      $params = [];
      $sql = "SELECT nome_usuario AS nome, status, equipe, posicao_fila, tempo_entrada, tempo_saida
              FROM controle_pausa";
      if ($equipe !== '') {
        $sql .= " WHERE equipe = ?";
        $params[] = $equipe;
      }
      $sql .= " ORDER BY
                 CASE status
                   WHEN 'ativo' THEN 0
                   WHEN 'espera' THEN 1
                   WHEN 'pausa' THEN 2
                   WHEN 'expirada' THEN 3
                 END ASC,
                 posicao_fila ASC,
                 tempo_entrada ASC";

      $st = $pdo->prepare($sql);
      $st->execute($params);
      $lista = $st->fetchAll(PDO::FETCH_ASSOC);

      // add tempo_espera_dinamico (segundos) para os que estão em espera
      foreach ($lista as &$r) {
        if ($r['status'] === 'espera') {
          // pegar linha completa para calcular com base no tempo_entrada atual
          $full = obterLinhaUsuario($pdo, $r['nome'], $r['equipe']);
          $r['tempo_espera_dinamico'] = calcularTempoEspera($full);
        }
      }

      jexit(true, ['estado' => $lista]);
    }

    // ---------------------------------------------------------
    // ENTRAR NA FILA (ou entrar direto em pausa se houver vaga)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'entrar_fila': {
      $nome  = trim($payload['nome'] ?? '');
      $equipe= trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);

      $pdo->beginTransaction();
      try {
        $row = garantirUsuario($pdo, $nome, $equipe);

        if ($row['status'] === 'pausa') {
          $pdo->commit();
          jexit(true, ['msg' => 'Já está em pausa.']);
        }
        if ($row['status'] === 'espera') {
          $pdo->commit();
          jexit(true, ['msg' => 'Já está na fila.']);
        }

        // Se houver vaga, entra direto em pausa
        if (contarPausasAtivas($pdo, $equipe) < MAX_PAUSAS_POR_EQUIPE) {
          $st = $pdo->prepare("UPDATE controle_pausa SET status='pausa', tempo_entrada=?, tempo_saida=NULL, posicao_fila=NULL, ultima_atualizacao=? WHERE id=?");
          $st->execute([agora(), agora(), $row['id']]);
          $pdo->commit();
          jexit(true, ['msg' => 'Entrou em pausa imediatamente.', 'status' => 'pausa']);
        }

        // Caso contrário, entra na fila ao final
        $pos = maxPosicaoFila($pdo, $equipe) + 1;
        $st = $pdo->prepare("UPDATE controle_pausa SET status='espera', tempo_entrada=?, tempo_saida=NULL, posicao_fila=?, ultima_atualizacao=? WHERE id=?");
        $st->execute([agora(), $pos, agora(), $row['id']]);
        $pdo->commit();
        jexit(true, ['msg' => 'Entrou na fila.', 'status' => 'espera', 'posicao' => $pos]);
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(false, ['error' => 'Falha ao entrar na fila.']);
      }
    }

    // ---------------------------------------------------------
    // FORÇAR PAUSA (compatível com v1.13 / admin)
    // POST JSON: { nome, equipe }
    // - Respeita vagas; se cheio, retorna erro (para não burlar regra)
    // ---------------------------------------------------------
    case 'forcar_pausa': {
      $nome  = trim($payload['nome'] ?? '');
      $equipe= trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);

      $pdo->beginTransaction();
      try {
        $row = garantirUsuario($pdo, $nome, $equipe);

        if (contarPausasAtivas($pdo, $equipe) >= MAX_PAUSAS_POR_EQUIPE) {
          $pdo->rollBack();
          jexit(false, ['error' => 'Limite de pausas atingido na equipe.']);
        }

        $st = $pdo->prepare("UPDATE controle_pausa SET status='pausa', tempo_entrada=?, tempo_saida=NULL, posicao_fila=NULL, ultima_atualizacao=? WHERE id=?");
        $st->execute([agora(), agora(), $row['id']]);

        // compacta fila se ele estava nela
        reordenarFilaCompacta($pdo, $equipe);

        $pdo->commit();
        jexit(true, ['msg' => 'Usuário colocado em pausa.', 'status' => 'pausa']);
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(false, ['error' => 'Não foi possível forçar a pausa.']);
      }
    }

    // ---------------------------------------------------------
    // VOLTAR A DISPONÍVEL (encerra pausa ou cancela espera)
    // POST JSON: { nome, equipe }
    // ---------------------------------------------------------
    case 'voltar_disponivel': {
      $nome  = trim($payload['nome'] ?? '');
      $equipe= trim($payload['equipe'] ?? '');
      if ($nome === '' || $equipe === '') jexit(false, ['error' => 'Nome e equipe são obrigatórios.']);

      $pdo->beginTransaction();
      try {
        $row = garantirUsuario($pdo, $nome, $equipe);

        // Se estava em espera, calcula tempo_espera
        if ($row['status'] === 'espera' && $row['tempo_entrada']) {
          $espera = calcularTempoEspera($row);
          $st = $pdo->prepare("UPDATE controle_pausa SET tempo_espera = tempo_espera + ?, tempo_saida = ?, status='ativo', posicao_fila=NULL, ultima_atualizacao=? WHERE id=?");
          $st->execute([$espera, agora(), agora(), $row['id']]);
        } else {
          // Se estava em pausa, registra saída
          $st = $pdo->prepare("UPDATE controle_pausa SET tempo_saida = ?, status='ativo', posicao_fila=NULL, ultima_atualizacao=? WHERE id=?");
          $st->execute([agora(), agora(), $row['id']]);
        }

        // reordena fila e promove se houver vaga
        reordenarFilaCompacta($pdo, $equipe);
        promoverFilaSeHouverVaga($pdo, $equipe);

        $pdo->commit();
        jexit(true, ['msg' => 'Status atualizado para ativo.', 'status' => 'ativo']);
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(false, ['error' => 'Falha ao voltar a disponível.']);
      }
    }

    // ---------------------------------------------------------
    // SOLICITAR TROCA (2º da fila pede ao 1º)
    // POST JSON: { equipe }
    // O solicitante é inferido via "solicitante" (nome no payload)
    // ---------------------------------------------------------
    case 'solicitar_troca': {
      $equipe = trim($payload['equipe'] ?? '');
      $solicitante = trim($payload['solicitante'] ?? ''); // quem clica (segundo)
      if ($equipe === '' || $solicitante === '') jexit(false, ['error' => 'Equipe e solicitante são obrigatórios.']);

      // obter top 2
      $top2 = topoFila($pdo, $equipe, 2);
      if (count($top2) < 2) jexit(false, ['error' => 'Não há 2 pessoas na fila.']);

      // validando: solicitante deve ser o segundo
      $segundo = $top2[1]['nome_usuario'];
      $primeiro= $top2[0]['nome_usuario'];

      if (normalizar($solicitante) !== normalizar($segundo)) {
        jexit(false, ['error' => 'Apenas o segundo da fila pode solicitar troca.']);
      }

      // cria solicitação pendente (se já não existir uma idêntica pendente)
      $st = $pdo->prepare("
        INSERT INTO solicitacoes_troca (equipe, primeiro, segundo, status, criado_em)
        VALUES (?, ?, ?, 'pendente', ?)
        ON DUPLICATE KEY UPDATE criado_em = VALUES(criado_em), status='pendente'
      ");
      $st->execute([$equipe, $primeiro, $segundo, agora()]);

      jexit(true, ['msg' => 'Solicitação enviada ao primeiro da fila.', 'primeiro' => $primeiro, 'segundo' => $segundo]);
    }

    // ---------------------------------------------------------
    // DECIDIR TROCA (primeiro escolhe: 'segundo' ou 'fim')
    // POST JSON: { equipe, decisao, decisor }
    // ---------------------------------------------------------
    case 'decidir_troca': {
      $equipe = trim($payload['equipe'] ?? '');
      $decisor= trim($payload['decisor'] ?? ''); // deve ser o primeiro atual
      $decisao= trim($payload['decisao'] ?? ''); // 'segundo' | 'fim'
      if ($equipe === '' || $decisor === '' || !in_array($decisao, ['segundo','fim'], true)) {
        jexit(false, ['error' => 'Parâmetros inválidos (equipe/decisor/decisao).']);
      }

      // confirmar top2
      $top2 = topoFila($pdo, $equipe, 2);
      if (count($top2) < 2) jexit(false, ['error' => 'Fila com menos de 2 pessoas.']);

      $primeiro = $top2[0]['nome_usuario'];
      $segundo  = $top2[1]['nome_usuario'];

      if (normalizar($decisor) !== normalizar($primeiro)) {
        jexit(false, ['error' => 'Apenas o primeiro atual pode decidir.']);
      }

      $pdo->beginTransaction();
      try {
        if ($decisao === 'segundo') {
          // swap posicoes de primeiro e segundo
          $pos1 = (int)$top2[0]['posicao_fila'];
          $pos2 = (int)$top2[1]['posicao_fila'];

          $u1 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u2 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u1->execute([$pos2, agora(), $top2[0]['id']]); // primeiro vira segundo
          $u2->execute([$pos1, agora(), $top2[1]['id']]); // segundo vira primeiro

        } else if ($decisao === 'fim') {
          // manda o primeiro para o fim
          $max = maxPosicaoFila($pdo, $equipe);
          $u = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = ?, ultima_atualizacao = ? WHERE id = ?");
          $u->execute([$max + 1, agora(), $top2[0]['id']]);
          // compacta para garantir ordem
          reordenarFilaCompacta($pdo, $equipe);
        }

        // marca solicitação como concluída/aceita
        $st = $pdo->prepare("
          UPDATE solicitacoes_troca
          SET status='concluida', escolha=?, respondido_em=?
          WHERE equipe=? AND primeiro=? AND segundo=? AND status='pendente'
          LIMIT 1
        ");
        $st->execute([$decisao, agora(), $equipe, $primeiro, $segundo]);

        $pdo->commit();
        jexit(true, ['msg' => 'Troca aplicada com sucesso.', 'decisao' => $decisao]);
      } catch (Throwable $e) {
        $pdo->rollBack();
        jexit(false, ['error' => 'Falha ao aplicar a troca.']);
      }
    }

    // ---------------------------------------------------------
    // EXPIRAR PAUSAS (cron/fetch): verifica pausas além do limite
    // GET/POST: [&limite_min=15][&equipe=...]
    // ---------------------------------------------------------
    case 'expirar_pausas': {
      $lim = (int)($_GET['limite_min'] ?? $payload['limite_min'] ?? DURACAO_PAUSA_PADRAO_MIN);
      $equipe = $_GET['equipe'] ?? ($payload['equipe'] ?? null);

      $condEquipe = '';
      $params = [$lim];
      if ($equipe) { $condEquipe = "AND equipe = ?"; $params[] = $equipe; }

      // pausa iniciada há > $lim minutos => expira
      $sql = "
        UPDATE controle_pausa
        SET status='expirada', tempo_saida = ?, ultima_atualizacao = ?
        WHERE status='pausa'
          AND TIMESTAMPDIFF(MINUTE, tempo_entrada, NOW()) >= ?
          {$condEquipe}
      ";
      $st = $pdo->prepare($sql);
      $st->execute(array_merge([agora(), agora()], $params));
      $qtd = $st->rowCount();

      // após expirar, promove fila (se houver)
      if ($equipe) {
        promoverFilaSeHouverVaga($pdo, $equipe);
      } else {
        // promover para todas as equipes com vagas
        // pega todas as equipes existentes
        $eq = $pdo->query("SELECT DISTINCT equipe FROM controle_pausa")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($eq as $eqp) promoverFilaSeHouverVaga($pdo, $eqp);
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
  jexit(false, ['error' => 'Erro inesperado no servidor.']);
}
