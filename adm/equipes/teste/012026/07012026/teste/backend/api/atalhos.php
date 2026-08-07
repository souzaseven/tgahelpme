<?php
// ============================================================
// atalhos.php — Atalhos de preenchimento automático: o usuário
// cadastra uma "descrição-chave" (ex: "Oferta Igreja") com valor,
// categoria, conta, cartão e responsável padrão; ao digitar essa
// descrição numa receita/despesa nova, os demais campos são
// preenchidos sozinhos (ver aplicarAutoPreenchimento() em
// pages/receitas.php e pages/despesas.php).
//
// GET  ?tipo=receita|despesa   — lista atalhos (com nomes p/ exibição)
// POST acao=salvar             — cria/edita
// POST acao=toggle             — ativa/desativa sem apagar
// DELETE ?id=X
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../helpers/normalizacao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: listar ───────────────────────────────────────────────
if ($metodo === 'GET') {
    $tipo = trim($_GET['tipo'] ?? '');

    $where  = ['1=1'];
    $params = [];
    if (in_array($tipo, ['receita', 'despesa'], true)) {
        $where[] = 'a.tipo = ?';
        $params[] = $tipo;
    }
    $wSql = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT a.*,
                c.nome  categoria_nome, c.cor cat_cor,
                ct.nome conta_nome,
                cc.nome cartao_nome,
                r.nome  responsavel_nome,
                tc.nome terceiro_nome
         FROM `{$p}atalhos_preenchimento` a
         LEFT JOIN `{$p}categorias`   c  ON c.id  = a.categoria_id
         LEFT JOIN `{$p}contas`      ct  ON ct.id = a.conta_id
         LEFT JOIN `{$p}cartoes`     cc  ON cc.id = a.cartao_id
         LEFT JOIN `{$p}responsaveis` r  ON r.id  = a.responsavel_id
         LEFT JOIN `{$p}terceiros`   tc  ON tc.id = a.terceiro_id
         WHERE $wSql
         ORDER BY a.ativo DESC, a.tipo, a.descricao_chave"
    );
    $stmt->execute($params);
    respostaJSON(['success' => true, 'dados' => $stmt->fetchAll()]);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    if ($acao === 'salvar') {
        $id     = (int)($d['id'] ?? 0);
        $tipo   = in_array($d['tipo'] ?? '', ['receita', 'despesa'], true) ? $d['tipo'] : 'despesa';
        $chave  = normalizarTexto($d['descricao_chave'] ?? '');

        if ($chave === '') {
            respostaJSON(['success' => false, 'erro' => 'A descrição-chave é obrigatória.']);
        }

        $valor    = isset($d['valor']) && $d['valor'] !== '' ? round((float)$d['valor'], 2) : null;
        $catId    = (int)($d['categoria_id']   ?? 0) ?: null;
        $contaId  = (int)($d['conta_id']       ?? 0) ?: null;
        $cartaoId = (int)($d['cartao_id']      ?? 0) ?: null;
        $respId   = (int)($d['responsavel_id'] ?? 0) ?: null;
        $tercId   = (int)($d['terceiro_id']    ?? 0) ?: null;
        $obs      = trim($d['observacao'] ?? '') ?: null;

        $dup = $pdo->prepare(
            "SELECT id FROM `{$p}atalhos_preenchimento` WHERE tipo=? AND descricao_chave=? AND id<>?"
        );
        $dup->execute([$tipo, $chave, $id]);
        if ($dup->fetchColumn()) {
            respostaJSON(['success' => false, 'erro' => 'Já existe um atalho com essa descrição para esse tipo.']);
        }

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}atalhos_preenchimento`
                 SET tipo=?, descricao_chave=?, valor=?, categoria_id=?, conta_id=?, cartao_id=?,
                     responsavel_id=?, terceiro_id=?, observacao=?, atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$tipo, $chave, $valor, $catId, $contaId, $cartaoId, $respId, $tercId, $obs, $id]);
            respostaJSON(['success' => true, 'msg' => 'Atalho atualizado.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}atalhos_preenchimento`
             (tipo, descricao_chave, valor, categoria_id, conta_id, cartao_id, responsavel_id, terceiro_id, observacao)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([$tipo, $chave, $valor, $catId, $contaId, $cartaoId, $respId, $tercId, $obs]);
        respostaJSON(['success' => true, 'msg' => 'Atalho cadastrado.']);
    }

    if ($acao === 'toggle') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $pdo->prepare("UPDATE `{$p}atalhos_preenchimento` SET ativo = 1 - ativo WHERE id=?")->execute([$id]);
        respostaJSON(['success' => true, 'msg' => 'Status alterado.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $pdo->prepare("DELETE FROM `{$p}atalhos_preenchimento` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Atalho removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
