<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: listar templates ─────────────────────────────────────
if ($metodo === 'GET') {
    $tipo = trim($_GET['tipo'] ?? '');
    $where  = $tipo ? "WHERE t.tipo = ?" : '';
    $params = $tipo ? [$tipo] : [];

    $stmt = $pdo->prepare(
        "SELECT t.*,
                c.nome  cat_nome,  c.cor  cat_cor,  c.icone cat_icone,
                ct.nome conta_nome,
                cc.nome cartao_nome,
                r.nome  resp_nome, r.cor  resp_cor, r.icone resp_icone
         FROM `{$p}templates` t
         LEFT JOIN `{$p}categorias`   c  ON c.id  = t.categoria_id
         LEFT JOIN `{$p}contas`       ct ON ct.id = t.conta_id
         LEFT JOIN `{$p}cartoes`      cc ON cc.id = t.cartao_id
         LEFT JOIN `{$p}responsaveis` r  ON r.id  = t.responsavel_id
         $where
         ORDER BY t.uso_count DESC, t.nome"
    );
    $stmt->execute($params);
    respostaJSON(['success' => true, 'dados' => $stmt->fetchAll()]);
}

// ── POST: salvar / registrar uso ──────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? 'salvar');

    if ($acao === 'registrar_uso') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $pdo->prepare("UPDATE `{$p}templates` SET uso_count = uso_count + 1, atualizado_em = NOW() WHERE id = ?")->execute([$id]);
        respostaJSON(['success' => true]);
    }

    $id   = (int)($d['id']   ?? 0);
    $nome = trim($d['nome']  ?? '');
    $desc = trim($d['descricao'] ?? '');
    if (!$nome || !$desc) respostaJSON(['success' => false, 'erro' => 'Nome e descrição são obrigatórios.']);

    $tipos = ['receita', 'despesa'];
    $tipo  = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'despesa';
    $valor = isset($d['valor']) && $d['valor'] !== '' ? round((float)$d['valor'], 2) : null;
    $catId = (int)($d['categoria_id']  ?? 0) ?: null;
    $ctId  = (int)($d['conta_id']      ?? 0) ?: null;
    $ccId  = (int)($d['cartao_id']     ?? 0) ?: null;
    $rId   = (int)($d['responsavel_id']?? 0) ?: null;
    $obs   = trim($d['observacao'] ?? '');

    if ($id > 0) {
        $pdo->prepare(
            "UPDATE `{$p}templates`
             SET nome=?, tipo=?, descricao=?, valor=?, categoria_id=?,
                 conta_id=?, cartao_id=?, responsavel_id=?, observacao=?, atualizado_em=NOW()
             WHERE id=?"
        )->execute([$nome, $tipo, $desc, $valor, $catId, $ctId, $ccId, $rId, $obs, $id]);
        respostaJSON(['success' => true, 'msg' => 'Template atualizado.']);
    }

    $pdo->prepare(
        "INSERT INTO `{$p}templates`
         (nome, tipo, descricao, valor, categoria_id, conta_id, cartao_id, responsavel_id, observacao)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([$nome, $tipo, $desc, $valor, $catId, $ctId, $ccId, $rId, $obs]);
    respostaJSON(['success' => true, 'msg' => 'Template salvo.', 'id' => (int)$pdo->lastInsertId()]);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
    $pdo->prepare("DELETE FROM `{$p}templates` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Template removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
