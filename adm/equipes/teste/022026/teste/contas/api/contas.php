<?php
// contas/api/contas.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function out($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function getJsonBody(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  return is_array($data) ? $data : [];
}

// Helpers
function validTipo(string $t): bool { return in_array($t, ['despesa','receita'], true); }

try {

  // LISTAR + FILTRAR
  if ($method === 'GET' && $action === 'list') {
    $mes = $_GET['mes'] ?? date('Y-m'); // ex: 2026-02
    $q = trim((string)($_GET['q'] ?? ''));
    $tipo = (string)($_GET['tipo'] ?? ''); // despesa/receita
    $pago = (string)($_GET['pago'] ?? ''); // 0/1
    $categoria = trim((string)($_GET['categoria'] ?? ''));
    $ordem = (string)($_GET['ordem'] ?? 'vencimento_asc'); // vencimento_asc|vencimento_desc|valor_desc|valor_asc

    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

    $where = [];
    $params = [];

    // Filtro por mês: vencimento entre primeiro e último dia
    $where[] = "vencimento >= :ini AND vencimento <= :fim";
    $params[':ini'] = $mes . "-01";
    $params[':fim'] = date('Y-m-t', strtotime($mes . "-01"));

    if ($q !== '') {
      $where[] = "(titulo LIKE :q OR observacao LIKE :q OR forma_pagamento LIKE :q)";
      $params[':q'] = "%{$q}%";
    }
    if ($tipo !== '' && validTipo($tipo)) {
      $where[] = "tipo = :tipo";
      $params[':tipo'] = $tipo;
    }
    if ($pago !== '' && ($pago === '0' || $pago === '1')) {
      $where[] = "pago = :pago";
      $params[':pago'] = (int)$pago;
    }
    if ($categoria !== '') {
      $where[] = "categoria = :cat";
      $params[':cat'] = $categoria;
    }

    $orderBy = "vencimento ASC, id DESC";
    if ($ordem === 'vencimento_desc') $orderBy = "vencimento DESC, id DESC";
    if ($ordem === 'valor_desc') $orderBy = "valor DESC, vencimento ASC";
    if ($ordem === 'valor_asc') $orderBy = "valor ASC, vencimento ASC";

    $sql = "SELECT * FROM contas WHERE " . implode(" AND ", $where) . " ORDER BY {$orderBy}";
    $stm = $pdo->prepare($sql);
    $stm->execute($params);
    $rows = $stm->fetchAll();

    // Totais do mês (receitas, despesas, saldo, pendente/pago)
    $tot = $pdo->prepare("
      SELECT
        SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) AS receitas,
        SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) AS despesas,
        SUM(CASE WHEN pago=1 THEN valor ELSE 0 END) AS total_pago,
        SUM(CASE WHEN pago=0 THEN valor ELSE 0 END) AS total_pendente
      FROM contas
      WHERE vencimento >= :ini AND vencimento <= :fim
    ");
    $tot->execute([':ini'=>$params[':ini'], ':fim'=>$params[':fim']]);
    $kpi = $tot->fetch() ?: ['receitas'=>0,'despesas'=>0,'total_pago'=>0,'total_pendente'=>0];
    $kpi = array_map(fn($v)=> (float)$v, $kpi);
    $kpi['saldo'] = (float)$kpi['receitas'] - (float)$kpi['despesas'];

    // Categorias do mês (para dropdown)
    $cats = $pdo->prepare("
      SELECT categoria, COUNT(*) qtd
      FROM contas
      WHERE vencimento >= :ini AND vencimento <= :fim
      GROUP BY categoria
      ORDER BY categoria ASC
    ");
    $cats->execute([':ini'=>$params[':ini'], ':fim'=>$params[':fim']]);
    $categorias = $cats->fetchAll();

    out([
      'success' => true,
      'data' => $rows,
      'kpi' => $kpi,
      'categorias' => $categorias,
    ]);
  }

  // CRIAR
  if ($method === 'POST' && $action === 'create') {
    $b = getJsonBody();

    $titulo = trim((string)($b['titulo'] ?? ''));
    $categoria = trim((string)($b['categoria'] ?? 'Geral')) ?: 'Geral';
    $tipo = (string)($b['tipo'] ?? 'despesa');
    $valor = (float)($b['valor'] ?? 0);
    $vencimento = (string)($b['vencimento'] ?? '');
    $pago = (int)($b['pago'] ?? 0);
    $data_pagamento = $b['data_pagamento'] ?? null;
    $forma = trim((string)($b['forma_pagamento'] ?? ''));
    $obs = trim((string)($b['observacao'] ?? ''));

    if ($titulo === '' || !validTipo($tipo) || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimento)) {
      out(['success'=>false,'message'=>'Preencha título, tipo, valor e vencimento corretamente.'], 422);
    }

    if ($pago !== 0 && $pago !== 1) $pago = 0;
    if ($pago === 1 && $data_pagamento === null) $data_pagamento = date('Y-m-d');
    if ($data_pagamento !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data_pagamento)) $data_pagamento = null;

    $stm = $pdo->prepare("
      INSERT INTO contas (titulo,categoria,tipo,valor,vencimento,pago,data_pagamento,forma_pagamento,observacao)
      VALUES (:t,:c,:tp,:v,:ven,:p,:dp,:f,:o)
    ");
    $stm->execute([
      ':t'=>$titulo, ':c'=>$categoria, ':tp'=>$tipo, ':v'=>$valor, ':ven'=>$vencimento,
      ':p'=>$pago, ':dp'=>$data_pagamento, ':f'=>($forma ?: null), ':o'=>($obs ?: null),
    ]);

    out(['success'=>true,'message'=>'Conta criada com sucesso.','id'=>$pdo->lastInsertId()]);
  }

  // EDITAR
  if ($method === 'PUT' && $action === 'update') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) out(['success'=>false,'message'=>'ID inválido.'], 422);

    $b = getJsonBody();

    $titulo = trim((string)($b['titulo'] ?? ''));
    $categoria = trim((string)($b['categoria'] ?? 'Geral')) ?: 'Geral';
    $tipo = (string)($b['tipo'] ?? 'despesa');
    $valor = (float)($b['valor'] ?? 0);
    $vencimento = (string)($b['vencimento'] ?? '');
    $pago = (int)($b['pago'] ?? 0);
    $data_pagamento = $b['data_pagamento'] ?? null;
    $forma = trim((string)($b['forma_pagamento'] ?? ''));
    $obs = trim((string)($b['observacao'] ?? ''));

    if ($titulo === '' || !validTipo($tipo) || $valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimento)) {
      out(['success'=>false,'message'=>'Preencha título, tipo, valor e vencimento corretamente.'], 422);
    }

    if ($pago !== 0 && $pago !== 1) $pago = 0;
    if ($pago === 1 && ($data_pagamento === null || $data_pagamento === '')) $data_pagamento = date('Y-m-d');
    if ($data_pagamento !== null && $data_pagamento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data_pagamento)) $data_pagamento = null;
    if ($pago === 0) $data_pagamento = null;

    $stm = $pdo->prepare("
      UPDATE contas
      SET titulo=:t, categoria=:c, tipo=:tp, valor=:v, vencimento=:ven, pago=:p,
          data_pagamento=:dp, forma_pagamento=:f, observacao=:o
      WHERE id=:id
      LIMIT 1
    ");
    $stm->execute([
      ':t'=>$titulo, ':c'=>$categoria, ':tp'=>$tipo, ':v'=>$valor, ':ven'=>$vencimento,
      ':p'=>$pago, ':dp'=>$data_pagamento, ':f'=>($forma ?: null), ':o'=>($obs ?: null),
      ':id'=>$id
    ]);

    out(['success'=>true,'message'=>'Conta atualizada.']);
  }

  // EXCLUIR
  if ($method === 'DELETE' && $action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) out(['success'=>false,'message'=>'ID inválido.'], 422);

    $stm = $pdo->prepare("DELETE FROM contas WHERE id=:id LIMIT 1");
    $stm->execute([':id'=>$id]);

    out(['success'=>true,'message'=>'Conta removida.']);
  }

  // ALTERAR PAGO RAPIDAMENTE
  if ($method === 'PATCH' && $action === 'toggle_pago') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) out(['success'=>false,'message'=>'ID inválido.'], 422);

    $b = getJsonBody();
    $pago = (int)($b['pago'] ?? 0);
    if ($pago !== 0 && $pago !== 1) $pago = 0;

    $dp = $pago === 1 ? date('Y-m-d') : null;

    $stm = $pdo->prepare("UPDATE contas SET pago=:p, data_pagamento=:dp WHERE id=:id LIMIT 1");
    $stm->execute([':p'=>$pago, ':dp'=>$dp, ':id'=>$id]);

    out(['success'=>true,'message'=>'Status atualizado.']);
  }

  out(['success'=>false,'message'=>'Rota inválida.'], 404);

} catch (Throwable $e) {
  out(['success'=>false,'message'=>'Erro interno no servidor.'], 500);
}