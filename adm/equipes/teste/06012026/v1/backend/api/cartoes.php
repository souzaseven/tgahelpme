<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];
$acao   = trim($_GET['acao'] ?? '');

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {

    // Lista todos os cartões ativos + resumo da fatura do mês
    if ($acao === 'lista') {
        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));

        $stmt = $pdo->prepare(
            "SELECT c.*,
                    f.id               fatura_id,
                    f.valor_total      fatura_total,
                    f.valor_pago       fatura_pago,
                    f.status           fatura_status,
                    f.data_vencimento  fatura_vencimento,
                    COALESCE(SUM(t.valor),0) gastos_mes
             FROM `{$p}cartoes` c
             LEFT JOIN `{$p}cartoes_faturas` f
                    ON f.cartao_id = c.id
                   AND f.mes_referencia = ? AND f.ano_referencia = ?
             LEFT JOIN `{$p}transacoes` t
                    ON t.cartao_id = c.id AND t.tipo = 'despesa'
                   AND MONTH(t.data) = ? AND YEAR(t.data) = ?
             WHERE c.ativo = 1
             GROUP BY c.id, f.id
             ORDER BY c.limite_total DESC"
        );
        $stmt->execute([$mes, $ano, $mes, $ano]);
        $cartoes = $stmt->fetchAll();

        $totais = $pdo->query(
            "SELECT COALESCE(SUM(limite_total),0) limite_total,
                    COALESCE(SUM(limite_usado),0)  limite_usado,
                    COUNT(*) qtd
             FROM `{$p}cartoes` WHERE ativo=1"
        )->fetch();

        respostaJSON(['success' => true, 'cartoes' => $cartoes, 'totais' => $totais]);
    }

    // Detalhe de um cartão: transações de um mês + info da fatura
    if ($acao === 'detalhe') {
        $id  = (int)($_GET['id']  ?? 0);
        $mes = (int)($_GET['mes'] ?? date('m'));
        $ano = (int)($_GET['ano'] ?? date('Y'));

        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $ccStmt = $pdo->prepare(
            "SELECT c.*,
                    f.id               fatura_id,
                    f.valor_total      fatura_total,
                    f.valor_pago       fatura_pago,
                    f.status           fatura_status,
                    f.data_vencimento  fatura_vencimento
             FROM `{$p}cartoes` c
             LEFT JOIN `{$p}cartoes_faturas` f
                    ON f.cartao_id = c.id
                   AND f.mes_referencia = ? AND f.ano_referencia = ?
             WHERE c.id = ?"
        );
        $ccStmt->execute([$mes, $ano, $id]);
        $cartao = $ccStmt->fetch();
        if (!$cartao) respostaJSON(['success' => false, 'erro' => 'Cartão não encontrado.']);

        $txStmt = $pdo->prepare(
            "SELECT t.*, c.nome cat_nome, c.cor cat_cor
             FROM `{$p}transacoes` t
             LEFT JOIN `{$p}categorias` c ON c.id = t.categoria_id
             WHERE t.cartao_id = ? AND t.tipo = 'despesa'
               AND MONTH(t.data) = ? AND YEAR(t.data) = ?
             ORDER BY t.data DESC, t.id DESC"
        );
        $txStmt->execute([$id, $mes, $ano]);
        $transacoes = $txStmt->fetchAll();

        $totalMes = array_sum(array_column($transacoes, 'valor'));

        respostaJSON([
            'success'    => true,
            'cartao'     => $cartao,
            'transacoes' => $transacoes,
            'total_mes'  => $totalMes,
        ]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // Cadastrar / atualizar cartão
    if ($acao === 'salvar') {
        $id   = (int)($d['id'] ?? 0);
        $nome = trim($d['nome'] ?? '');
        if (!$nome) respostaJSON(['success' => false, 'erro' => 'Nome é obrigatório.']);

        $bandeiras  = ['visa','mastercard','elo','amex','hipercard','outro'];
        $bandeira   = in_array($d['bandeira'] ?? '', $bandeiras) ? $d['bandeira'] : 'visa';
        $banco      = trim($d['banco']          ?? '');
        $digitos    = substr(preg_replace('/\D/', '', $d['ultimos_digitos'] ?? ''), 0, 4);
        $limite     = round(abs((float)($d['limite_total']    ?? 0)), 2);
        $diaFech    = max(1, min(31, (int)($d['dia_fechamento'] ?? 1)));
        $diaVenc    = max(1, min(31, (int)($d['dia_vencimento'] ?? 10)));
        $cor        = preg_match('/^#[0-9a-fA-F]{6}$/', $d['cor'] ?? '') ? $d['cor'] : '#8b5cf6';

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}cartoes`
                 SET nome=?, bandeira=?, banco=?, ultimos_digitos=?, limite_total=?,
                     dia_fechamento=?, dia_vencimento=?, cor=?, atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$nome, $bandeira, $banco, $digitos, $limite, $diaFech, $diaVenc, $cor, $id]);
            respostaJSON(['success' => true, 'msg' => 'Cartão atualizado.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}cartoes`
             (nome, bandeira, banco, ultimos_digitos, limite_total, dia_fechamento, dia_vencimento, cor)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$nome, $bandeira, $banco, $digitos, $limite, $diaFech, $diaVenc, $cor]);
        respostaJSON(['success' => true, 'msg' => 'Cartão cadastrado com sucesso.']);
    }

    // Registrar pagamento de fatura
    if ($acao === 'pagar_fatura') {
        $cartaoId  = (int)($d['cartao_id']      ?? 0);
        $mes       = (int)($d['mes']            ?? 0);
        $ano       = (int)($d['ano']            ?? 0);
        $valorPago = round(abs((float)($d['valor_pago'] ?? 0)), 2);
        $dataPag   = $d['data_pagamento']        ?? date('Y-m-d');
        $contaId   = (int)($d['conta_id']       ?? 0) ?: null;

        if (!$cartaoId || !$mes || !$ano || !$valorPago) {
            respostaJSON(['success' => false, 'erro' => 'Dados incompletos.']);
        }

        $pdo->beginTransaction();
        try {
            // Total real das transações do mês
            $s = $pdo->prepare(
                "SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes`
                 WHERE cartao_id=? AND tipo='despesa' AND MONTH(data)=? AND YEAR(data)=?"
            );
            $s->execute([$cartaoId, $mes, $ano]);
            $totalMes = (float) $s->fetchColumn();

            // Datas da fatura a partir do cartão
            $cc = $pdo->prepare("SELECT dia_fechamento, dia_vencimento FROM `{$p}cartoes` WHERE id=?");
            $cc->execute([$cartaoId]);
            $dias = $cc->fetch();

            $dataFech = sprintf('%04d-%02d-%02d', $ano, $mes, min($dias['dia_fechamento'], 28));
            $mesVenc  = $mes == 12 ? 1 : $mes + 1;
            $anoVenc  = $mes == 12 ? $ano + 1 : $ano;
            $dataVenc = sprintf('%04d-%02d-%02d', $anoVenc, $mesVenc, min($dias['dia_vencimento'], 28));

            // Busca fatura existente
            $f = $pdo->prepare(
                "SELECT * FROM `{$p}cartoes_faturas`
                 WHERE cartao_id=? AND mes_referencia=? AND ano_referencia=?"
            );
            $f->execute([$cartaoId, $mes, $ano]);
            $fatura = $f->fetch();

            if ($fatura) {
                $totalPago  = (float)$fatura['valor_pago'] + $valorPago;
                $novoStatus = $totalPago >= $totalMes - 0.01 ? 'paga' : 'parcial';
                $pdo->prepare(
                    "UPDATE `{$p}cartoes_faturas`
                     SET valor_total=?, valor_pago=?, status=?, atualizado_em=NOW()
                     WHERE id=?"
                )->execute([$totalMes, $totalPago, $novoStatus, $fatura['id']]);
            } else {
                $novoStatus = $valorPago >= $totalMes - 0.01 ? 'paga' : 'parcial';
                $pdo->prepare(
                    "INSERT INTO `{$p}cartoes_faturas`
                     (cartao_id, mes_referencia, ano_referencia, data_fechamento, data_vencimento,
                      valor_total, valor_pago, status)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([$cartaoId, $mes, $ano, $dataFech, $dataVenc,
                            $totalMes, $valorPago, $novoStatus]);
            }

            // Debita da conta
            if ($contaId) {
                $pdo->prepare(
                    "UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?"
                )->execute([$valorPago, $contaId]);
            }

            // Reduz limite_usado do cartão
            $pdo->prepare(
                "UPDATE `{$p}cartoes`
                 SET limite_usado = GREATEST(0, limite_usado - ?), atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$valorPago, $cartaoId]);

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Pagamento registrado com sucesso.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE: desativar cartão ──────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $pdo->prepare(
        "UPDATE `{$p}cartoes` SET ativo=0, atualizado_em=NOW() WHERE id=?"
    )->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Cartão desativado.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
