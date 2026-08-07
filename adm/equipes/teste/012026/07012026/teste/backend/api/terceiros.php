<?php
// ============================================================
// terceiros.php — Controle financeiro de gente de fora (cliente,
// fornecedor, um amigo que você adianta dinheiro) sem misturar com
// as finanças pessoais do usuário. Diferente de "responsáveis" —
// ver backend/api/responsaveis.php — que é para dividir despesas
// dentro do próprio grupo/família.
//
// "Saldo atual" (total_receitas/total_despesas na ação `lista`) é
// sempre acumulado geral, só com status='pago', independente de
// qualquer filtro de mês — não confundir com "previsão"
// (previsao_receitas/previsao_despesas), que inclui pendente.
//
// GET  ?acao=lista                    — terceiros + saldo/previsão de cada um
// GET  ?acao=transacoes&id=X&de=&ate= — lançamentos + KPI de um terceiro no período
// POST acao=salvar                    — cria/edita o terceiro
// POST acao=lancar                    — registra uma despesa/receita pra ele
// POST acao=excluir_tx / excluir_tx_bulk
// POST acao=alterar_status_tx_bulk
// DELETE ?id=X                        — desativa (não é hard delete)
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../helpers/normalizacao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];
$acao   = trim($_GET['acao'] ?? 'lista');

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {

    // Lista todos os terceiros com resumo financeiro
    if ($acao === 'lista') {
        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));

        $stmt = $pdo->query(
            "SELECT t.*,
                    (SELECT COALESCE(SUM(tx.valor),0) FROM `{$p}transacoes` tx
                     WHERE tx.terceiro_id = t.id AND tx.tipo='receita' AND tx.status='pago') total_receitas,
                    (SELECT COALESCE(SUM(tx.valor),0) FROM `{$p}transacoes` tx
                     WHERE tx.terceiro_id = t.id AND tx.tipo='despesa' AND tx.status='pago') total_despesas,
                    (SELECT COALESCE(SUM(tx.valor),0) FROM `{$p}transacoes` tx
                     WHERE tx.terceiro_id = t.id AND tx.tipo='receita' AND tx.status != 'cancelado') previsao_receitas,
                    (SELECT COALESCE(SUM(tx.valor),0) FROM `{$p}transacoes` tx
                     WHERE tx.terceiro_id = t.id AND tx.tipo='despesa' AND tx.status != 'cancelado') previsao_despesas,
                    (SELECT COUNT(*) FROM `{$p}transacoes` tx
                     WHERE tx.terceiro_id = t.id) total_transacoes
             FROM `{$p}terceiros` t
             WHERE t.ativo = 1
             ORDER BY t.nome"
        );
        respostaJSON(['success' => true, 'dados' => $stmt->fetchAll()]);
    }

    // Transações de um terceiro específico
    if ($acao === 'transacoes') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $de  = $_GET['de']  ?? date('Y-m-01');
        $ate = $_GET['ate'] ?? date('Y-m-t');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  $de  = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) $ate = date('Y-m-t');

        $stmt = $pdo->prepare(
            "SELECT t.*, c.nome cat_nome, c.cor cat_cor, ct.nome conta_nome, cc.nome cartao_nome
             FROM `{$p}transacoes` t
             LEFT JOIN `{$p}categorias` c  ON c.id  = t.categoria_id
             LEFT JOIN `{$p}contas`     ct ON ct.id = t.conta_id
             LEFT JOIN `{$p}cartoes`    cc ON cc.id = t.cartao_id
             WHERE t.terceiro_id = ? AND t.data BETWEEN ? AND ?
             ORDER BY t.data DESC, t.id DESC"
        );
        $stmt->execute([$id, $de, $ate]);
        $rows = $stmt->fetchAll();

        $kpi = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor END),0) receitas,
                    COALESCE(SUM(CASE WHEN tipo='despesa' AND status!='cancelado' THEN valor END),0) despesas,
                    COUNT(*) qtd
             FROM `{$p}transacoes`
             WHERE terceiro_id=? AND data BETWEEN ? AND ?"
        );
        $kpi->execute([$id, $de, $ate]);

        respostaJSON(['success' => true, 'transacoes' => $rows, 'kpi' => $kpi->fetch()]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // Salvar terceiro
    if ($acao === 'salvar') {
        $id   = (int)($d['id'] ?? 0);
        $nome = trim($d['nome'] ?? '');
        if (!$nome) respostaJSON(['success' => false, 'erro' => 'Nome é obrigatório.']);

        $desc  = trim($d['descricao'] ?? '');
        $cor   = corOuPadrao($d['cor'] ?? null, '#6366f1');
        $icone = iconeOuPadrao($d['icone'] ?? null, 'user');

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}terceiros` SET nome=?, descricao=?, cor=?, icone=?, atualizado_em=NOW() WHERE id=?"
            )->execute([$nome, $desc, $cor, $icone, $id]);
            respostaJSON(['success' => true, 'msg' => 'Terceiro atualizado.']);
        }
        $pdo->prepare(
            "INSERT INTO `{$p}terceiros` (nome, descricao, cor, icone) VALUES (?,?,?,?)"
        )->execute([$nome, $desc, $cor, $icone]);
        respostaJSON(['success' => true, 'msg' => 'Terceiro cadastrado.', 'id' => (int)$pdo->lastInsertId()]);
    }

    // Lançar transação para um terceiro
    if ($acao === 'lancar') {
        $terceiroId = (int)($d['terceiro_id'] ?? 0);
        $descricao  = trim($d['descricao'] ?? '');
        $valor      = round(abs((float)($d['valor'] ?? 0)), 2);
        $data       = $d['data'] ?? date('Y-m-d');
        $tipo       = in_array($d['tipo'] ?? '', ['receita','despesa']) ? $d['tipo'] : 'despesa';

        if (!$terceiroId || !$descricao || !$valor) {
            respostaJSON(['success' => false, 'erro' => 'Terceiro, descrição e valor são obrigatórios.']);
        }

        $catId    = (int)($d['categoria_id']  ?? 0) ?: null;
        $contaId  = (int)($d['conta_id']      ?? 0) ?: null;
        $cartaoId = (int)($d['cartao_id']     ?? 0) ?: null;
        $status   = in_array($d['status'] ?? '', ['pendente','pago','cancelado']) ? $d['status'] : 'pago';
        $obs      = trim($d['observacao'] ?? '');

        $pdo->prepare(
            "INSERT INTO `{$p}transacoes`
             (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id, status, observacao, terceiro_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$tipo, $descricao, $valor, $data, $catId, $contaId, $cartaoId, $status, $obs, $terceiroId]);

        respostaJSON(['success' => true, 'msg' => 'Lançamento registrado.']);
    }

    // Excluir transação de terceiro
    if ($acao === 'excluir_tx') {
        $id = (int)($d['id'] ?? 0);
        $terceiroId = (int)($d['terceiro_id'] ?? 0);
        if (!$id || !$terceiroId) respostaJSON(['success' => false, 'erro' => 'IDs inválidos.']);

        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id=? AND terceiro_id=?")->execute([$id, $terceiroId]);
        respostaJSON(['success' => true, 'msg' => 'Lançamento removido.']);
    }

    // Excluir transações em lote de um terceiro
    if ($acao === 'excluir_tx_bulk') {
        $terceiroId = (int)($d['terceiro_id'] ?? 0);
        $ids = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        if (!$terceiroId || !$ids) respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id IN ($ph) AND terceiro_id=?")
            ->execute([...$ids, $terceiroId]);
        respostaJSON(['success' => true, 'msg' => count($ids) . ' lançamento(s) excluído(s).']);
    }

    // Alterar status em lote de um terceiro
    if ($acao === 'alterar_status_tx_bulk') {
        $terceiroId = (int)($d['terceiro_id'] ?? 0);
        $ids    = array_values(array_filter(array_map('intval', $d['ids'] ?? []), fn($i) => $i > 0));
        $status = in_array($d['status'] ?? '', ['pago','pendente','cancelado']) ? $d['status'] : null;
        if (!$terceiroId || !$ids || !$status) respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE `{$p}transacoes` SET status=?, atualizado_em=NOW() WHERE id IN ($ph) AND terceiro_id=?")
            ->execute([$status, ...$ids, $terceiroId]);
        respostaJSON(['success' => true, 'msg' => count($ids) . ' lançamento(s) atualizado(s).']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE: desativar terceiro ─────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
    $pdo->prepare("UPDATE `{$p}terceiros` SET ativo=0, atualizado_em=NOW() WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Terceiro removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
