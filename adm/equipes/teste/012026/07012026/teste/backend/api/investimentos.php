<?php
// ============================================================
// investimentos.php — Carteira de investimentos (renda fixa, ações,
// fundos, cripto etc). `valor_aplicado` é o quanto entrou de capital
// próprio; `valor_atual` é o valor de mercado hoje — a diferença
// entre os dois é o rendimento exibido no card. Cada movimentação
// (aporte, resgate, dividendo, taxa...) fica registrada em
// investimentos_transacoes e ajusta esses dois totais de forma
// diferente dependendo do tipo (ver o switch em acao=transacao).
//
// GET  ?acao=lista                    — carteira + totais + agrupamento por tipo
// GET  ?acao=transacoes&id=X          — histórico de movimentações de um investimento
// POST acao=salvar                    — cria (com aporte inicial) ou edita metadados
// POST acao=transacao                 — registra aporte/resgate/rendimento/taxa
// POST acao=cotacao                   — atualiza o valor de mercado manualmente
// DELETE ?id=X                        — arquiva (não é hard delete)
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao = trim($_GET['acao'] ?? 'lista');

    if ($acao === 'lista') {
        $stmt = $pdo->query(
            "SELECT i.*, ct.nome conta_nome
             FROM `{$p}investimentos` i
             LEFT JOIN `{$p}contas` ct ON ct.id = i.conta_id
             WHERE i.ativo = 1
             ORDER BY i.valor_atual DESC"
        );
        $invs = $stmt->fetchAll();

        $totAplicado = array_sum(array_column($invs, 'valor_aplicado'));
        $totAtual    = array_sum(array_column($invs, 'valor_atual'));
        $rendimento  = $totAtual - $totAplicado;
        $rentab      = $totAplicado > 0 ? ($rendimento / $totAplicado) * 100 : 0;

        // Agrupamento por tipo para gráfico
        $porTipo = [];
        foreach ($invs as $inv) {
            $t = $inv['tipo'];
            $porTipo[$t] = ($porTipo[$t] ?? 0) + (float)$inv['valor_atual'];
        }

        respostaJSON([
            'success'    => true,
            'dados'      => $invs,
            'totais'     => [
                'aplicado'  => $totAplicado,
                'atual'     => $totAtual,
                'rendimento'=> $rendimento,
                'rentab'    => round($rentab, 2),
            ],
            'por_tipo'   => $porTipo,
        ]);
    }

    if ($acao === 'transacoes') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $stmt = $pdo->prepare(
            "SELECT * FROM `{$p}investimentos_transacoes`
             WHERE investimento_id = ? ORDER BY data DESC, id DESC"
        );
        $stmt->execute([$id]);
        respostaJSON(['success' => true, 'transacoes' => $stmt->fetchAll()]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Salvar investimento ────────────────────────────────────
    if ($acao === 'salvar') {
        $id      = (int)($d['id']      ?? 0);
        $nome    = trim($d['nome']     ?? '');
        $valor   = round(abs((float)($d['valor_aplicado'] ?? 0)), 2);
        $dataApl = $d['data_aplicacao'] ?? '';

        if (!$nome || !$dataApl) {
            respostaJSON(['success' => false, 'erro' => 'Nome e data de aplicação são obrigatórios.']);
        }

        $tipos  = ['tesouro','cdb','lci','lca','lc','debenture','fundo','acao','fii','etf','cripto','poupanca','outro'];
        $tipo   = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'outro';
        $ticker    = trim($d['ticker']         ?? '');
        $corretora = trim($d['corretora']      ?? '');
        $rentTipo  = in_array($d['rentabilidade_tipo'] ?? '',
                        ['prefixado','pos_cdi','pos_ipca','pos_selic','variavel'])
                     ? $d['rentabilidade_tipo'] : 'pos_cdi';
        $rentTaxa  = !empty($d['rentabilidade_taxa']) ? (float)$d['rentabilidade_taxa'] : null;
        $dataVenc  = !empty($d['data_vencimento']) ? $d['data_vencimento'] : null;
        $liquidez  = in_array($d['liquidez'] ?? '',
                        ['diaria','30d','60d','90d','180d','365d','vencimento'])
                     ? $d['liquidez'] : 'vencimento';
        $contaId   = (int)($d['conta_id'] ?? 0) ?: null;
        $qtd       = !empty($d['quantidade']) ? (float)$d['quantidade'] : null;

        if ($id > 0) {
            // Atualização: apenas metadados, não altera valores financeiros
            $pdo->prepare(
                "UPDATE `{$p}investimentos`
                 SET nome=?, tipo=?, ticker=?, corretora=?, rentabilidade_tipo=?,
                     rentabilidade_taxa=?, data_vencimento=?, liquidez=?, conta_id=?,
                     atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$nome, $tipo, $ticker, $corretora, $rentTipo,
                        $rentTaxa, $dataVenc, $liquidez, $contaId, $id]);
            respostaJSON(['success' => true, 'msg' => 'Investimento atualizado.']);
        }

        if ($valor <= 0) respostaJSON(['success' => false, 'erro' => 'Valor inicial é obrigatório.']);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO `{$p}investimentos`
                 (nome, tipo, ticker, corretora, valor_aplicado, valor_atual, quantidade,
                  rentabilidade_tipo, rentabilidade_taxa, data_aplicacao, data_vencimento,
                  liquidez, conta_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $nome, $tipo, $ticker, $corretora, $valor, $valor, $qtd,
                $rentTipo, $rentTaxa, $dataApl, $dataVenc, $liquidez, $contaId,
            ]);
            $invId = (int) $pdo->lastInsertId();

            // Registra aporte inicial
            $pdo->prepare(
                "INSERT INTO `{$p}investimentos_transacoes`
                 (investimento_id, tipo, valor, quantidade, preco_unitario, data, descricao)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $invId, 'aporte', $valor, $qtd,
                ($qtd > 0 ? round($valor / $qtd, 8) : null),
                $dataApl, 'Aporte inicial',
            ]);

            // Debita da conta (dinheiro saiu para investir)
            if ($contaId) {
                $pdo->prepare(
                    "UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?"
                )->execute([$valor, $contaId]);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Investimento cadastrado com sucesso.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    // ── Registrar movimentação ─────────────────────────────────
    if ($acao === 'transacao') {
        $invId   = (int)($d['investimento_id'] ?? 0);
        $tipoTx  = trim($d['tipo']  ?? '');
        $valor   = round(abs((float)($d['valor'] ?? 0)), 2);
        $data    = $d['data'] ?? date('Y-m-d');
        $desc    = trim($d['descricao'] ?? '');
        $qtd     = !empty($d['quantidade'])    ? (float)$d['quantidade']    : null;
        $preco   = !empty($d['preco_unitario'])? (float)$d['preco_unitario']: null;
        $contaId = (int)($d['conta_id'] ?? 0) ?: null;

        $tiposValidos = ['aporte','resgate','dividendo','juros','rendimento','taxa','outro'];
        if (!$invId || !in_array($tipoTx, $tiposValidos) || !$valor) {
            respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO `{$p}investimentos_transacoes`
                 (investimento_id, tipo, valor, quantidade, preco_unitario, data, descricao)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$invId, $tipoTx, $valor, $qtd, $preco, $data, $desc]);

            // Atualiza o investimento conforme o tipo
            switch ($tipoTx) {
                case 'aporte':
                    $pdo->prepare(
                        "UPDATE `{$p}investimentos`
                         SET valor_aplicado = valor_aplicado + ?,
                             valor_atual    = valor_atual    + ?,
                             quantidade     = COALESCE(quantidade, 0) + COALESCE(?, 0),
                             atualizado_em  = NOW()
                         WHERE id=?"
                    )->execute([$valor, $valor, $qtd, $invId]);
                    if ($contaId) {
                        $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                            ->execute([$valor, $contaId]);
                    }
                    break;

                case 'resgate':
                    // Reduz valor_aplicado proporcionalmente (baseado na fatia resgatada / valor_atual)
                    $inv = $pdo->prepare("SELECT valor_aplicado, valor_atual, quantidade FROM `{$p}investimentos` WHERE id=?");
                    $inv->execute([$invId]);
                    $row = $inv->fetch();
                    $proporcao = $row['valor_atual'] > 0 ? min(1, $valor / $row['valor_atual']) : 0;
                    $redAplic  = round($row['valor_aplicado'] * $proporcao, 2);

                    $pdo->prepare(
                        "UPDATE `{$p}investimentos`
                         SET valor_aplicado = GREATEST(0, valor_aplicado - ?),
                             valor_atual    = GREATEST(0, valor_atual    - ?),
                             quantidade     = IF(? IS NOT NULL, GREATEST(0, COALESCE(quantidade,0) - ?), quantidade),
                             atualizado_em  = NOW()
                         WHERE id=?"
                    )->execute([$redAplic, $valor, $qtd, $qtd, $invId]);
                    if ($contaId) {
                        $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                            ->execute([$valor, $contaId]);
                    }
                    break;

                case 'dividendo':
                case 'juros':
                case 'rendimento':
                    // Rendimento: aumenta valor_atual, não valor_aplicado
                    $pdo->prepare(
                        "UPDATE `{$p}investimentos`
                         SET valor_atual = valor_atual + ?, atualizado_em=NOW()
                         WHERE id=?"
                    )->execute([$valor, $invId]);
                    if ($contaId) {
                        $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?")
                            ->execute([$valor, $contaId]);
                    }
                    break;

                case 'taxa':
                    $pdo->prepare(
                        "UPDATE `{$p}investimentos`
                         SET valor_atual = GREATEST(0, valor_atual - ?), atualizado_em=NOW()
                         WHERE id=?"
                    )->execute([$valor, $invId]);
                    if ($contaId) {
                        $pdo->prepare("UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?")
                            ->execute([$valor, $contaId]);
                    }
                    break;
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Movimentação registrada.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    // ── Atualizar cotação / valor de mercado ───────────────────
    if ($acao === 'cotacao') {
        $id         = (int)($d['id'] ?? 0);
        $valorAtual = round(abs((float)($d['valor_atual'] ?? 0)), 2);
        if (!$id || !$valorAtual) respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);

        $pdo->prepare(
            "UPDATE `{$p}investimentos` SET valor_atual=?, atualizado_em=NOW() WHERE id=?"
        )->execute([$valorAtual, $id]);
        respostaJSON(['success' => true, 'msg' => 'Cotação atualizada.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE: desativar investimento ────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $pdo->prepare(
        "UPDATE `{$p}investimentos` SET ativo=0, atualizado_em=NOW() WHERE id=?"
    )->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Investimento arquivado.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
