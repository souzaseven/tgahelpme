<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: listar ───────────────────────────────────────────────
if ($metodo === 'GET') {
    $rows = $pdo->query(
        "SELECT r.*,
                COUNT(t.id) total_transacoes,
                COALESCE(SUM(CASE WHEN t.tipo='despesa' AND t.status!='cancelado' THEN t.valor END),0) total_despesas,
                COALESCE(SUM(CASE WHEN t.tipo='receita' AND t.status!='cancelado' THEN t.valor END),0) total_receitas
         FROM `{$p}responsaveis` r
         LEFT JOIN `{$p}transacoes` t ON t.responsavel_id = r.id
         GROUP BY r.id
         ORDER BY r.ativo DESC, r.nome"
    )->fetchAll();
    respostaJSON(['success' => true, 'dados' => $rows]);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    if ($acao === 'salvar') {
        $id   = (int)($d['id']   ?? 0);
        $nome = trim($d['nome']  ?? '');
        if (!$nome) respostaJSON(['success' => false, 'erro' => 'Nome é obrigatório.']);

        $icone = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($d['icone'] ?? ''))) ?: 'user';
        $cor   = preg_match('/^#[0-9a-fA-F]{6}$/', $d['cor'] ?? '') ? $d['cor'] : '#6366f1';

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}responsaveis` SET nome=?, cor=?, icone=? WHERE id=?"
            )->execute([$nome, $cor, $icone, $id]);
            respostaJSON(['success' => true, 'msg' => 'Responsável atualizado.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}responsaveis` (nome, cor, icone) VALUES (?,?,?)"
        )->execute([$nome, $cor, $icone]);
        respostaJSON(['success' => true, 'msg' => 'Responsável cadastrado.']);
    }

    if ($acao === 'toggle') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $pdo->prepare("UPDATE `{$p}responsaveis` SET ativo = 1 - ativo WHERE id=?")->execute([$id]);
        respostaJSON(['success' => true, 'msg' => 'Status alterado.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $pdo->prepare("UPDATE `{$p}transacoes`   SET responsavel_id=NULL WHERE responsavel_id=?")->execute([$id]);
    $pdo->prepare("UPDATE `{$p}recorrencias` SET responsavel_id=NULL WHERE responsavel_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM `{$p}responsaveis` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Responsável removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
