<?php
/* =========================================================
   API Plantões de Fim de Semana (Sáb/Dom)
   Autor: Anderson de Souza
   Status: PRODUÇÃO (ATUALIZADO)
   Base REAL: suportes_plantao + plantoes_fim_semana
========================================================= */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexao.php'; // também carrega bootstrap (env, sessão, timezone)

/* =========================================================
   HELPERS
========================================================= */
function jexit($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function method() {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function getJsonBody() {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function csrf_ok() {
  // getallheaders() às vezes falha; HTTP_X_CSRF_TOKEN é mais estável
  $hdr  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $sess = $_SESSION['csrf_token'] ?? '';
  return $hdr && $sess && hash_equals($sess, $hdr);
}

function require_admin(): void {
  if (empty($_SESSION['admin_logged'])) {
    jexit(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.'], 401);
  }
}

function ymd($s) {
  $d = DateTime::createFromFormat('Y-m-d', (string)$s);
  return $d && $d->format('Y-m-d') === $s;
}

function isSaturday($s): bool {
  $d = DateTime::createFromFormat('Y-m-d', (string)$s);
  return $d && $d->format('Y-m-d') === $s && $d->format('N') === '6';
}

/* =========================================================
   FUNÇÕES DE DATA / FIM DE SEMANA
========================================================= */
function nextSaturdayFrom(DateTime $d): DateTime {
  $x = clone $d;
  $x->setTime(0,0,0);
  $dow = (int)$x->format('N'); // 1=Seg ... 6=Sáb 7=Dom
  $add = ($dow <= 6) ? (6 - $dow) : 6; // se domingo, +6
  if ($add > 0) $x->modify("+{$add} day");
  return $x;
}

function weekendRangeFromSaturday(DateTime $sat): array {
  $s = clone $sat;
  $s->setTime(0,0,0);
  $sun = clone $s;
  $sun->modify('+1 day');
  return [
    'sabado'  => $s->format('Y-m-d'),
    'domingo' => $sun->format('Y-m-d'),
  ];
}

function isWeekendToday(): bool {
  $now = new DateTime('now');
  $n = (int)$now->format('N'); // 6 sab, 7 dom
  return ($n === 6 || $n === 7);
}

/*
  Definição usada no painel:
  - "Fim de semana atual":
      - se hoje é sábado/domingo: o fim de semana de hoje
      - senão: o próximo fim de semana
  - "Semana passada": anterior ao atual
  - "Próximo": após o atual
*/
function getPrevCurrentNextWeekends(): array {
  $now = new DateTime('now');
  $baseSat = null;

  if (isWeekendToday()) {
    $baseSat = clone $now;
    $baseSat->setTime(0,0,0);
    $dow = (int)$baseSat->format('N');
    if ($dow === 7) $baseSat->modify('-1 day'); // domingo -> sábado foi ontem
  } else {
    $baseSat = nextSaturdayFrom($now);
  }

  $prevSat = (clone $baseSat)->modify('-7 day');
  $nextSat = (clone $baseSat)->modify('+7 day');

  return [
    'prev'    => weekendRangeFromSaturday($prevSat),
    'current' => weekendRangeFromSaturday($baseSat),
    'next'    => weekendRangeFromSaturday($nextSat),
  ];
}

/* =========================================================
   BANCO
========================================================= */
function getPlantaoBySaturday(PDO $pdo, string $sabadoYmd) {
  $sql = "
    SELECT id, sabado, domingo, observacao,
           suporte_id, suporte_nome
    FROM plantoes_fim_semana
    WHERE sabado = :sab
    LIMIT 1
  ";

  $st = $pdo->prepare($sql);
  $st->execute([':sab' => $sabadoYmd]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

/* =========================================================
   ROUTER
========================================================= */
$action = $_GET['action'] ?? '';

try {

  /* ==========================
     SEGURANÇA
     - ações de escrita exigem admin autenticado
     - 'stats' é restrito ao painel interno
     - todo POST exige token CSRF válido
  ========================== */
  $writeActions = ['support_save', 'support_delete', 'plantao_save', 'plantao_delete'];
  $adminOnlyGet = ['stats', 'ranking'];

  if (in_array($action, $writeActions, true) || in_array($action, $adminOnlyGet, true)) {
    require_admin();
  }

  if (method() === 'POST' && !csrf_ok()) {
    jexit(['success' => false, 'error' => 'CSRF inválido. Recarregue a página.'], 403);
  }

  /* ==========================
     GET: LISTAR SUPORTES
     /?action=supports_list&only_active=0|1
  ========================== */
  if ($action === 'supports_list' && method() === 'GET') {
    $onlyActive = ($_GET['only_active'] ?? '1') === '1';

    // visitantes não autenticados só enxergam colaboradores ativos
    if (!$onlyActive && empty($_SESSION['admin_logged'])) {
      $onlyActive = true;
    }

    $sql = "
      SELECT id, nome, ativo, criado_em, atualizado_em
      FROM suportes_plantao
      " . ($onlyActive ? "WHERE ativo = 1" : "") . "
      ORDER BY nome ASC
    ";

    $st = $pdo->query($sql);
    jexit(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
  }

  /* ==========================
     POST: SALVAR SUPORTE (ADD/EDIT)
     action=support_save
  ========================== */
  if ($action === 'support_save' && method() === 'POST') {
    $b = getJsonBody();
    $id    = (int)($b['id'] ?? 0);
    $nome  = trim((string)($b['nome'] ?? ''));
    $ativo = ((int)($b['ativo'] ?? 1) ? 1 : 0);

    if ($nome === '') jexit(['success' => false, 'error' => 'Nome do suporte é obrigatório.'], 400);

    if ($id > 0) {
      $st = $pdo->prepare("UPDATE suportes_plantao SET nome=:n, ativo=:a WHERE id=:id");
      $st->execute([':n'=>$nome, ':a'=>$ativo, ':id'=>$id]);
      jexit(['success'=>true, 'message'=>'Suporte atualizado.']);
    }

    $st = $pdo->prepare("INSERT INTO suportes_plantao (nome, ativo) VALUES (:n, :a)");
    $st->execute([':n'=>$nome, ':a'=>$ativo]);

    jexit(['success'=>true, 'message'=>'Suporte cadastrado.', 'id'=>(int)$pdo->lastInsertId()]);
  }

  /* ==========================
     GET: RESUMO (3 CARDS)
     action=summary
  ========================== */
  if ($action === 'summary' && method() === 'GET') {
    $w = getPrevCurrentNextWeekends();

    $prev = getPlantaoBySaturday($pdo, $w['prev']['sabado']);
    $cur  = getPlantaoBySaturday($pdo, $w['current']['sabado']);
    $next = getPlantaoBySaturday($pdo, $w['next']['sabado']);

    jexit([
      'success' => true,
      'weekends' => [
        'prev' => ['range' => $w['prev'],    'plantao' => $prev],
        'current' => ['range' => $w['current'], 'plantao' => $cur],
        'next' => ['range' => $w['next'],    'plantao' => $next],
      ],
      'horarios' => [
        'sabado' => '13:00 às 18:00',
        'domingo' => '07:30 às 11:30'
      ]
    ]);
  }

  /* ==========================
     GET: LISTAR FINS DE SEMANA DO MÊS
     action=month&ym=YYYY-MM
  ========================== */
  if ($action === 'month' && method() === 'GET') {
    $ym = $_GET['ym'] ?? '';
    if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) {
      jexit(['success'=>false, 'error'=>'Parâmetro ym inválido (use YYYY-MM).'], 400);
    }

    $first = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    $first->setTime(0,0,0);
    $last = (clone $first)->modify('last day of this month')->setTime(0,0,0);

    // Primeiro sábado que toca o mês (ou o anterior)
    $start = clone $first;
    $startDow = (int)$start->format('N');
    $start->modify('-' . ($startDow - 1) . ' day'); // volta para segunda
    $startSat = nextSaturdayFrom($start);

    // Último sábado que toca o mês
    $endSat = nextSaturdayFrom($last);
    if ($endSat > $last) $endSat->modify('-7 day');

    $sats = [];
    $it = clone $startSat;
    while ($it <= $endSat) {
      $range = weekendRangeFromSaturday($it);
      $sab = $range['sabado'];
      $dom = $range['domingo'];

      $mSab = substr($sab, 0, 7);
      $mDom = substr($dom, 0, 7);

      if ($mSab === $ym || $mDom === $ym) $sats[] = $sab;
      $it->modify('+7 day');
    }
// Buscar plantões existentes
$items = [];
if ($sats) {
  $in = implode(',', array_fill(0, count($sats), '?'));

  $sql = "
    SELECT id, sabado, domingo, observacao,
           suporte_id, suporte_nome
    FROM plantoes_fim_semana
    WHERE sabado IN ($in)
    ORDER BY sabado ASC
  ";

  $st = $pdo->prepare($sql);
  $st->execute($sats);

  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $items[$r['sabado']] = $r;
  }
}


    // Retornar todos os fins de semana, com/sem plantão
    $out = [];
    foreach ($sats as $sab) {
      $range = weekendRangeFromSaturday(DateTime::createFromFormat('Y-m-d', $sab));
      $out[] = [
        'sabado' => $range['sabado'],
        'domingo' => $range['domingo'],
        'plantao' => $items[$sab] ?? null,
      ];
    }

    jexit(['success'=>true, 'ym'=>$ym, 'data'=>$out]);
  }

/* ==========================
   GET: LISTAR POR PERÍODO
   action=period&start=...&end=...
   (INTERVALOS QUE SE CRUZAM)
========================== */
if ($action === 'period' && method() === 'GET') {

  $start = trim($_GET['start'] ?? '');
  $end   = trim($_GET['end'] ?? '');

  // aceita YYYY-MM-DD ou DD/MM/YYYY
  $toYMD = function(string $v) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
      $d = DateTime::createFromFormat('d/m/Y', $v);
      return $d ? $d->format('Y-m-d') : '';
    }
    return '';
  };

  $startYMD = $toYMD($start);
  $endYMD   = $toYMD($end);

  if (!$startYMD || !$endYMD) {
    jexit(['success'=>false,'error'=>'Datas inválidas. Use YYYY-MM-DD (ou DD/MM/YYYY).'], 400);
  }

  // garante ordem
  if ($startYMD > $endYMD) {
    [$startYMD, $endYMD] = [$endYMD, $startYMD];
  }

  $sql = "
    SELECT id, sabado, domingo, observacao,
           suporte_id, suporte_nome
    FROM plantoes_fim_semana
    WHERE sabado <= :fim
      AND domingo >= :ini
    ORDER BY sabado ASC
    LIMIT 2000
  ";

  $st = $pdo->prepare($sql);
  $st->execute([
    ':ini' => $startYMD,
    ':fim' => $endYMD
  ]);

  jexit(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}



  /* ==========================
     POST: SALVAR PLANTÃO (ADD/EDIT)
     action=plantao_save
     ✅ AGORA permite QUALQUER data futura (sem travas)
  ========================== */
  if ($action === 'plantao_save' && method() === 'POST') {
    $b = getJsonBody();

    $sabado    = (string)($b['sabado'] ?? '');
    $suporteId = (int)($b['suporte_id'] ?? 0);
    $observacao = trim((string)($b['observacao'] ?? ''));

    if (!isSaturday($sabado)) jexit(['success'=>false, 'error'=>'A data do plantão precisa ser um sábado (YYYY-MM-DD).'], 400);
    if ($suporteId <= 0) jexit(['success'=>false, 'error'=>'Selecione um suporte.'], 400);

    // ✅ Permitir somente datas futuras (se quiser permitir passado também, remova este bloco)
    $today = new DateTime('today');
    $sabDT = DateTime::createFromFormat('Y-m-d', $sabado);
    $sabDT->setTime(0,0,0);

    if ($sabDT < $today) {
      jexit(['success'=>false, 'error'=>'Não é permitido cadastrar plantão em data passada.'], 400);
    }

    // validar suporte existe
    $st = $pdo->prepare("SELECT id FROM suportes_plantao WHERE id=:id");
    $st->execute([':id'=>$suporteId]);
    if (!$st->fetch(PDO::FETCH_ASSOC)) jexit(['success'=>false, 'error'=>'Suporte não encontrado.'], 404);

    // calcular domingo
    $range = weekendRangeFromSaturday($sabDT);
    $domingo = $range['domingo'];

// buscar nome do suporte
$st = $pdo->prepare("SELECT nome FROM suportes_plantao WHERE id = :id");
$st->execute([':id' => $suporteId]);
$suporteNome = $st->fetchColumn();

if (!$suporteNome) {
  jexit(['success'=>false, 'error'=>'Suporte não encontrado.'], 404);
}

    // upsert atômico por sábado — requer UNIQUE KEY em `sabado` (ver schema.sql)
    $st = $pdo->prepare("
      INSERT INTO plantoes_fim_semana (sabado, domingo, suporte_id, suporte_nome, observacao)
      VALUES (:sab, :dom, :sid, :sname, :obs)
      ON DUPLICATE KEY UPDATE
        domingo      = VALUES(domingo),
        suporte_id   = VALUES(suporte_id),
        suporte_nome = VALUES(suporte_nome),
        observacao   = VALUES(observacao)
    ");

    $st->execute([
      ':sab'   => $sabado,
      ':dom'   => $domingo,
      ':sid'   => $suporteId,
      ':sname' => $suporteNome,
      ':obs'   => ($observacao !== '' ? $observacao : null),
    ]);

    // rowCount(): 1 = inseriu um novo registro, 2 = atualizou um existente (MySQL)
    $inseriu = $st->rowCount() === 1;

    jexit([
      'success' => true,
      'message' => $inseriu ? 'Plantão cadastrado.' : 'Plantão atualizado.',
      'id'      => (int)$pdo->lastInsertId(),
    ]);
  }

  /* ==========================
     POST: REMOVER PLANTÃO
     action=plantao_delete
     ✅ deixa "ninguém no plantão"
  ========================== */
  if ($action === 'plantao_delete' && method() === 'POST') {
    $b = getJsonBody();
    $sabado = (string)($b['sabado'] ?? '');

    if (!ymd($sabado)) jexit(['success'=>false, 'error'=>'Sábado inválido (YYYY-MM-DD).'], 400);

    $st = $pdo->prepare("DELETE FROM plantoes_fim_semana WHERE sabado=:sab");
    $st->execute([':sab'=>$sabado]);

    jexit(['success'=>true, 'message'=>'Plantão removido.']);
  }

  /* ==========================
     GET: ESTATÍSTICAS POR COLABORADOR
     action=stats&ym=YYYY-MM
  ========================== */
  if ($action === 'stats' && method() === 'GET') {
    $ym = trim($_GET['ym'] ?? date('Y-m'));

    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
      jexit(['success' => false, 'error' => 'Parâmetro ym inválido (use YYYY-MM).'], 400);
    }

    $sql = "
      SELECT suporte_id, suporte_nome, COUNT(*) AS total
      FROM plantoes_fim_semana
      WHERE DATE_FORMAT(sabado, '%Y-%m') = :ym
        AND suporte_id IS NOT NULL
      GROUP BY suporte_id, suporte_nome
      ORDER BY total DESC, suporte_nome ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([':ym' => $ym]);

    jexit(['success' => true, 'ym' => $ym, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
  }

  /* ==========================
     GET: RANKING GERAL POR COLABORADOR
     action=ranking[&start=YYYY-MM-DD&end=YYYY-MM-DD]
     - sem start/end => todo o período
  ========================== */
  if ($action === 'ranking' && method() === 'GET') {
    $start = trim($_GET['start'] ?? '');
    $end   = trim($_GET['end']   ?? '');

    $where  = 'suporte_id IS NOT NULL';
    $params = [];

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
      if ($start > $end) {
        [$start, $end] = [$end, $start];
      }
      $where .= ' AND sabado BETWEEN :ini AND :fim';
      $params[':ini'] = $start;
      $params[':fim'] = $end;
    }

    $sql = "
      SELECT suporte_id,
             suporte_nome,
             COUNT(*)    AS total,
             MIN(sabado) AS primeiro,
             MAX(sabado) AS ultimo
      FROM plantoes_fim_semana
      WHERE {$where}
      GROUP BY suporte_id, suporte_nome
      ORDER BY total DESC, suporte_nome ASC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $totalPlantoes = array_sum(array_map(static fn($r) => (int)$r['total'], $rows));

    $years = $pdo
      ->query("SELECT DISTINCT YEAR(sabado) AS y FROM plantoes_fim_semana ORDER BY y DESC")
      ->fetchAll(PDO::FETCH_COLUMN);

    jexit([
      'success'             => true,
      'total_plantoes'      => $totalPlantoes,
      'total_colaboradores' => count($rows),
      'years'               => array_map('intval', $years),
      'data'                => $rows,
    ]);
  }

  /* ==========================
     POST: EXCLUIR COLABORADOR
     body: { id }
  ========================== */
  if ($action === 'support_delete' && method() === 'POST') {
    $b  = getJsonBody();
    $id = (int)($b['id'] ?? 0);

    if ($id <= 0) jexit(['success' => false, 'error' => 'ID inválido.'], 400);

    // Bloqueia exclusão se houver plantões vinculados
    $st = $pdo->prepare("SELECT COUNT(*) FROM plantoes_fim_semana WHERE suporte_id = :id");
    $st->execute([':id' => $id]);
    $count = (int)$st->fetchColumn();

    if ($count > 0) {
      jexit([
        'success' => false,
        'error'   => "Não é possível excluir: este colaborador possui {$count} plantão(ões) registrado(s). Desative-o ou remova os plantões primeiro."
      ], 409);
    }

    $pdo->prepare("DELETE FROM suportes_plantao WHERE id = :id")->execute([':id' => $id]);

    jexit(['success' => true, 'message' => 'Colaborador excluído com sucesso.']);
  }

  jexit(['success'=>false, 'error'=>'Ação inválida.'], 404);

} catch (Throwable $e) {
  error_log('[plantoes_api.php] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
  jexit(['success' => false, 'error' => 'Erro interno no servidor. Tente novamente.'], 500);
}
