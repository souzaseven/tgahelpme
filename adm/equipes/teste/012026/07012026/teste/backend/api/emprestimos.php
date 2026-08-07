<?php
// ============================================================
// emprestimos.php — Empréstimos e financiamentos com amortização
// pela Tabela Price (parcelas fixas, juros compostos sobre o saldo
// devedor). Ao criar ou editar um empréstimo, a tabela inteira é
// gerada de uma vez e cada parcela vira uma despesa "pendente" já
// sincronizada (mesmo id de transação), pra aparecer normalmente
// nas telas de Despesas e no calendário.
//
// GET  ?acao=lista            — empréstimos ativos + totais gerais
// GET  ?acao=parcelas&id=X    — parcelas de um empréstimo
// POST acao=criar             — cadastra e gera a tabela Price
// POST acao=editar_completo   — recalcula a tabela do zero (mudou valor/taxa/prazo)
// POST acao=atualizar         — só dados cadastrais, não mexe na tabela financeira
// POST acao=pagar_parcela     — baixa uma parcela (com suporte a antecipação/quitação)
// DELETE ?id=X                — remove o empréstimo e as parcelas ainda não pagas
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../../src/Financeiro/Calculos.php';

use FinanceOS\Financeiro\Calculos;

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * Gera a tabela de amortização pelo Sistema Price (parcelas fixas):
 * PMT = PV × [i(1+i)ⁿ] / [(1+i)ⁿ − 1], onde a cada parcela os juros
 * incidem sobre o saldo devedor restante e a amortização é o
 * complemento até fechar o valor fixo da parcela. A última parcela
 * absorve a diferença de arredondamento para o saldo fechar em zero.
 */
function gerarTabelaPrice(
    float $pv, float $taxaMensal, int $n,
    string $dataInicio, int $diaVenc
): array {
    $rows = [];

    if ($taxaMensal <= 0 || $pv <= 0 || $n <= 0) {
        // Sem juros: parcela fixa simples
        $pmt   = round($pv / $n, 2);
        $saldo = $pv;
        for ($k = 1; $k <= $n; $k++) {
            $amort = ($k === $n) ? $saldo : $pmt;
            $saldo = round($saldo - $amort, 2);
            $rows[] = [
                'numero'            => $k,
                'data_vencimento'   => dataVenc($dataInicio, $k, $diaVenc),
                'valor_parcela'     => $pmt,
                'valor_amortizacao' => $amort,
                'valor_juros'       => 0.00,
                'saldo_devedor'     => max(0, $saldo),
            ];
        }
        return $rows;
    }

    $i     = $taxaMensal / 100;         // taxa mensal em decimal
    $fator = pow(1 + $i, $n);            // (1+i)ⁿ
    $pmt   = round($pv * ($i * $fator) / ($fator - 1), 2); // PMT da fórmula Price
    $saldo = $pv;

    for ($k = 1; $k <= $n; $k++) {
        $juros = round($saldo * $i, 2);
        $amort = ($k === $n) ? $saldo : round($pmt - $juros, 2);
        $parc  = ($k === $n) ? round($amort + $juros, 2) : $pmt;
        $saldo = round(max(0, $saldo - $amort), 2);

        $rows[] = [
            'numero'            => $k,
            'data_vencimento'   => dataVenc($dataInicio, $k, $diaVenc),
            'valor_parcela'     => $parc,
            'valor_amortizacao' => $amort,
            'valor_juros'       => $juros,
            'saldo_devedor'     => $saldo,
        ];
    }
    return $rows;
}

// ── Cria transações pendentes + parcelas sincronizadas ───────
function inserirParcelasComTransacoes(
    PDO $pdo, string $p, int $empId, string $nomeEmp,
    array $tabela, ?int $contaId
): void {
    $total = count($tabela);
    $insT  = $pdo->prepare(
        "INSERT INTO `{$p}transacoes` (tipo, descricao, valor, data, conta_id, status)
         VALUES ('despesa', ?, ?, ?, ?, 'pendente')"
    );
    $insP  = $pdo->prepare(
        "INSERT INTO `{$p}emprestimos_parcelas`
         (emprestimo_id, numero, data_vencimento, valor_parcela,
          valor_amortizacao, valor_juros, saldo_devedor, transacao_id)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    foreach ($tabela as $row) {
        $insT->execute([
            'Parcela ' . $row['numero'] . '/' . $total . ' — ' . $nomeEmp,
            $row['valor_parcela'],
            $row['data_vencimento'],
            $contaId,
        ]);
        $tId = (int)$pdo->lastInsertId();
        $insP->execute([
            $empId, $row['numero'], $row['data_vencimento'],
            $row['valor_parcela'], $row['valor_amortizacao'],
            $row['valor_juros'], $row['saldo_devedor'], $tId,
        ]);
    }
}

// ── Retorna IDs de transações pendentes de um empréstimo ─────
function getPendingTransacaoIds(PDO $pdo, string $p, int $empId): array {
    $stmt = $pdo->prepare(
        "SELECT ep.transacao_id
         FROM `{$p}emprestimos_parcelas` ep
         JOIN `{$p}transacoes` t ON t.id = ep.transacao_id
         WHERE ep.emprestimo_id = ? AND t.status = 'pendente' AND ep.transacao_id IS NOT NULL"
    );
    $stmt->execute([$empId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'transacao_id');
}

// ── Deleta transações pendentes por IDs ───────────────────────
function deletePendingTransacoes(PDO $pdo, string $p, array $ids): void {
    if (!$ids) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id IN ($ph)")->execute($ids);
}

function dataVenc(string $dataInicio, int $numero, int $diaVenc): string
{
    $dt  = new DateTime($dataInicio);
    $dt->modify("+{$numero} months");
    $ano = (int) $dt->format('Y');
    $mes = (int) $dt->format('m');
    $dia = min($diaVenc, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));
    return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
}

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao = trim($_GET['acao'] ?? 'lista');

    if ($acao === 'lista') {
        $status = trim($_GET['status'] ?? '');
        $statusValidos = ['ativo', 'quitado', 'atrasado', 'negociado', 'cancelado'];
        if ($status && !in_array($status, $statusValidos)) $status = '';

        $whereClause = $status ? 'WHERE e.status = ?' : "WHERE e.status != 'quitado'";
        $stmt = $pdo->prepare(
            "SELECT e.*,
                    ct.nome conta_nome,
                    (SELECT ep.data_vencimento FROM `{$p}emprestimos_parcelas` ep
                     WHERE ep.emprestimo_id = e.id AND ep.status = 'pendente'
                     ORDER BY ep.numero LIMIT 1) proxima_data,
                    (SELECT ep.valor_parcela FROM `{$p}emprestimos_parcelas` ep
                     WHERE ep.emprestimo_id = e.id AND ep.status = 'pendente'
                     ORDER BY ep.numero LIMIT 1) proxima_valor,
                    (SELECT ep.numero FROM `{$p}emprestimos_parcelas` ep
                     WHERE ep.emprestimo_id = e.id AND ep.status = 'pendente'
                     ORDER BY ep.numero LIMIT 1) proxima_numero,
                    (SELECT COUNT(*) FROM `{$p}emprestimos_parcelas` ep
                     WHERE ep.emprestimo_id = e.id AND ep.status = 'pendente'
                       AND ep.data_vencimento < CURDATE()) parcelas_atraso
             FROM `{$p}emprestimos` e
             LEFT JOIN `{$p}contas` ct ON ct.id = e.conta_debito_id
             $whereClause
             ORDER BY e.status DESC, e.data_fim"
        );
        $stmt->execute($status ? [$status] : []);
        $emprestimos = $stmt->fetchAll();

        $totais = $pdo->query(
            "SELECT COALESCE(SUM(saldo_devedor),0)  saldo_total,
                    COALESCE(SUM(valor_parcela),0)   parcela_total,
                    COUNT(*) qtd,
                    SUM(CASE WHEN status='atrasado' THEN 1 ELSE 0 END) atrasados
             FROM `{$p}emprestimos` WHERE status NOT IN ('quitado','cancelado')"
        )->fetch();

        respostaJSON(['success' => true, 'emprestimos' => $emprestimos, 'totais' => $totais]);
    }

    if ($acao === 'parcelas') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $stmt = $pdo->prepare(
            "SELECT * FROM `{$p}emprestimos_parcelas`
             WHERE emprestimo_id = ? ORDER BY numero"
        );
        $stmt->execute([$id]);
        respostaJSON(['success' => true, 'parcelas' => $stmt->fetchAll()]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Criar empréstimo ───────────────────────────────────────
    if ($acao === 'criar') {
        $nome     = trim($d['nome']   ?? '');
        $valor    = round(abs((float)($d['valor_contratado'] ?? 0)), 2);
        $taxa     = abs((float)($d['taxa_juros_mensal'] ?? 0));
        $parcelas = max(1, (int)($d['total_parcelas']   ?? 1));
        $dataIni  = $d['data_inicio'] ?? '';
        $diaVenc  = max(1, min(31, (int)($d['dia_vencimento'] ?? 10)));

        if (!$nome || !$valor || !$dataIni) {
            respostaJSON(['success' => false, 'erro' => 'Nome, valor e data de início são obrigatórios.']);
        }

        $tipos    = ['pessoal','veiculo','imovel','consignado','cartao','outro'];
        $tipo     = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'pessoal';
        $credor   = trim($d['credor']  ?? '');
        $cet      = !empty($d['cet_mensal']) ? (float)$d['cet_mensal'] : null;
        $contaId  = (int)($d['conta_debito_id'] ?? 0) ?: null;
        $obs      = trim($d['observacao'] ?? '');

        // Calcula PMT e saldo devedor
        $tabela   = gerarTabelaPrice($valor, $taxa, $parcelas, $dataIni, $diaVenc);
        $pmt      = $tabela[0]['valor_parcela'];
        $dataFim  = $tabela[$parcelas - 1]['data_vencimento'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO `{$p}emprestimos`
                 (nome, tipo, credor, valor_contratado, valor_parcela, total_parcelas,
                  taxa_juros_mensal, cet_mensal, saldo_devedor, data_inicio, data_fim,
                  dia_vencimento, conta_debito_id, observacao)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $nome, $tipo, $credor, $valor, $pmt, $parcelas,
                $taxa, $cet, $valor, $dataIni, $dataFim,
                $diaVenc, $contaId, $obs,
            ]);
            $empId = (int) $pdo->lastInsertId();

            // Cria parcelas + despesas pendentes sincronizadas
            inserirParcelasComTransacoes($pdo, $p, $empId, $nome, $tabela, $contaId);

            // Registrar entrada do valor como receita (opcional)
            if (!empty($d['registrar_entrada']) && $contaId) {
                $pdo->prepare(
                    "INSERT INTO `{$p}transacoes`
                     (tipo, descricao, valor, data, conta_id, status)
                     VALUES ('receita', ?, ?, ?, ?, 'pago')"
                )->execute(['Empréstimo recebido: ' . $nome, $valor, $dataIni, $contaId]);
                $pdo->prepare(
                    "UPDATE `{$p}contas` SET saldo_atual = saldo_atual + ? WHERE id=?"
                )->execute([$valor, $contaId]);
            }

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Empréstimo cadastrado com tabela Price gerada.', 'id' => $empId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    // ── Editar completo — regera toda a tabela de amortização ─
    if ($acao === 'editar_completo') {
        $id       = (int)($d['id']                          ?? 0);
        $nome     = trim($d['nome']                         ?? '');
        $valor    = round(abs((float)($d['valor_contratado'] ?? 0)), 2);
        $taxa     = abs((float)($d['taxa_juros_mensal']      ?? 0));
        $parcelas = max(1, (int)($d['total_parcelas']        ?? 1));
        $dataIni  = $d['data_inicio']                       ?? '';
        $diaVenc  = max(1, min(31, (int)($d['dia_vencimento'] ?? 10)));

        if (!$id || !$nome || !$valor || !$dataIni) {
            respostaJSON(['success' => false, 'erro' => 'Nome, valor e data de início são obrigatórios.']);
        }

        $tipos   = ['pessoal','veiculo','imovel','consignado','cartao','outro'];
        $tipo    = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'pessoal';
        $credor  = trim($d['credor']   ?? '');
        $contaId = (int)($d['conta_debito_id'] ?? 0) ?: null;
        $obs     = trim($d['observacao'] ?? '');
        $status  = in_array($d['status'] ?? '', ['ativo','quitado','atrasado','negociado'])
                   ? $d['status'] : 'ativo';

        $tabela  = gerarTabelaPrice($valor, $taxa, $parcelas, $dataIni, $diaVenc);
        $pmt     = $tabela[0]['valor_parcela'];
        $dataFim = $tabela[$parcelas - 1]['data_vencimento'];

        $pdo->beginTransaction();
        try {
            // Coleta transações pendentes antes de deletar as parcelas
            $pendingIds = getPendingTransacaoIds($pdo, $p, $id);

            // Remove todas as parcelas antigas (incluindo pagas)
            $pdo->prepare("DELETE FROM `{$p}emprestimos_parcelas` WHERE emprestimo_id=?")->execute([$id]);

            // Remove apenas as transações pendentes (pagas são histórico e ficam)
            deletePendingTransacoes($pdo, $p, $pendingIds);

            // Atualiza registro principal com todos os campos
            $pdo->prepare(
                "UPDATE `{$p}emprestimos`
                 SET nome=?, tipo=?, credor=?, valor_contratado=?, valor_parcela=?,
                     total_parcelas=?, taxa_juros_mensal=?, cet_mensal=NULL,
                     saldo_devedor=?, data_inicio=?, data_fim=?, dia_vencimento=?,
                     conta_debito_id=?, observacao=?, status=?,
                     parcelas_pagas=0, atualizado_em=NOW()
                 WHERE id=?"
            )->execute([
                $nome, $tipo, $credor, $valor, $pmt,
                $parcelas, $taxa,
                $valor, $dataIni, $dataFim, $diaVenc,
                $contaId, $obs, $status, $id,
            ]);

            // Recria parcelas + despesas pendentes sincronizadas
            inserirParcelasComTransacoes($pdo, $p, $id, $nome, $tabela, $contaId);

            $pdo->commit();
            respostaJSON(['success' => true, 'msg' => 'Empréstimo atualizado e tabela recalculada.']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    // ── Atualizar dados cadastrais (sem mexer na tabela financeira) ──
    if ($acao === 'atualizar') {
        $id      = (int)($d['id']      ?? 0);
        $nome    = trim($d['nome']     ?? '');
        $credor  = trim($d['credor']   ?? '');
        $contaId = (int)($d['conta_debito_id'] ?? 0) ?: null;
        $obs     = trim($d['observacao'] ?? '');
        $status  = in_array($d['status'] ?? '', ['ativo','quitado','atrasado','negociado'])
                   ? $d['status'] : null;

        if (!$id || !$nome) respostaJSON(['success' => false, 'erro' => 'ID e nome são obrigatórios.']);

        $setSt = $status ? ', status=?' : '';
        $vals  = [$nome, $credor, $contaId, $obs];
        if ($status) $vals[] = $status;
        $vals[] = $id;

        $pdo->prepare(
            "UPDATE `{$p}emprestimos`
             SET nome=?, credor=?, conta_debito_id=?, observacao=?$setSt, atualizado_em=NOW()
             WHERE id=?"
        )->execute($vals);
        respostaJSON(['success' => true, 'msg' => 'Empréstimo atualizado.']);
    }

    // ── Pagar parcela ──────────────────────────────────────────
    if ($acao === 'pagar_parcela') {
        $parcelaId  = (int)($d['parcela_id']    ?? 0);
        $dataPag    = $d['data_pagamento']       ?? date('Y-m-d');
        $contaId    = (int)($d['conta_id']       ?? 0) ?: null;

        if (!$parcelaId) respostaJSON(['success' => false, 'erro' => 'ID da parcela inválido.']);

        $ep = $pdo->prepare(
            "SELECT ep.*, e.nome emp_nome, e.conta_debito_id, e.total_parcelas, e.parcelas_pagas,
                    e.saldo_devedor emp_saldo_devedor
             FROM `{$p}emprestimos_parcelas` ep
             JOIN `{$p}emprestimos` e ON e.id = ep.emprestimo_id
             WHERE ep.id = ?"
        );
        $ep->execute([$parcelaId]);
        $parcela = $ep->fetch();
        if (!$parcela) respostaJSON(['success' => false, 'erro' => 'Parcela não encontrada.']);
        if (in_array($parcela['status'], ['pago','antecipado'])) respostaJSON(['success' => false, 'erro' => 'Parcela já paga.']);

        // Valor efetivamente pago (pode ser diferente na antecipação)
        $valorPago = isset($d['valor_pago']) && (float)$d['valor_pago'] > 0
                     ? round((float)$d['valor_pago'], 2)
                     : (float)$parcela['valor_parcela'];

        // Antecipação: pagou antes do vencimento
        $antecipado = $dataPag < $parcela['data_vencimento'];
        $novoStatus = $antecipado ? 'antecipado' : 'pago';

        $ctPag = $contaId ?: ($parcela['conta_debito_id'] ?: null);

        $descricaoParcela = 'Parcela ' . $parcela['numero'] . '/' . $parcela['total_parcelas']
                          . ' — ' . $parcela['emp_nome']
                          . ($antecipado ? ' (antecipada)' : '');

        $pdo->beginTransaction();
        try {
            // Atualiza a transação pendente existente (sincronizada) ou cria se não houver
            if ($parcela['transacao_id']) {
                $pdo->prepare(
                    "UPDATE `{$p}transacoes`
                     SET status='pago', data=?, valor=?, conta_id=?
                     WHERE id=?"
                )->execute([$dataPag, $valorPago, $ctPag, $parcela['transacao_id']]);
                $transacaoId = $parcela['transacao_id'];
            } else {
                // Fallback: empréstimo criado antes da sincronização automática
                $pdo->prepare(
                    "INSERT INTO `{$p}transacoes`
                     (tipo, descricao, valor, data, conta_id, status)
                     VALUES ('despesa', ?, ?, ?, ?, 'pago')"
                )->execute([$descricaoParcela, $valorPago, $dataPag, $ctPag]);
                $transacaoId = (int)$pdo->lastInsertId();
            }

            // Marca parcela como paga e vincula à transação
            $pdo->prepare(
                "UPDATE `{$p}emprestimos_parcelas`
                 SET status=?, data_pagamento=?, valor_pago=?, transacao_id=? WHERE id=?"
            )->execute([$novoStatus, $dataPag, $valorPago, $transacaoId, $parcelaId]);

            // Atualiza o empréstimo
            $novasPagas      = (int)$parcela['parcelas_pagas'] + 1;
            $saldoAntes      = (float)$parcela['emp_saldo_devedor'];
            // Quitamento antecipado total: valor pago cobre o saldo devedor restante
            $quitandoTotal   = Calculos::quitaTotal($valorPago, $saldoAntes);
            $quitado         = $novasPagas >= (int)$parcela['total_parcelas'] || $quitandoTotal;

            if ($quitandoTotal) {
                // Zera saldo e marca como quitado
                $pdo->prepare(
                    "UPDATE `{$p}emprestimos`
                     SET parcelas_pagas=?, saldo_devedor=0, status='quitado', atualizado_em=NOW()
                     WHERE id=?"
                )->execute([$novasPagas, $parcela['emprestimo_id']]);

                // Cancela todas as demais parcelas ainda pendentes
                $rstmt = $pdo->prepare(
                    "SELECT ep.id, ep.transacao_id
                     FROM `{$p}emprestimos_parcelas` ep
                     WHERE ep.emprestimo_id=? AND ep.status='pendente' AND ep.id != ?"
                );
                $rstmt->execute([$parcela['emprestimo_id'], $parcelaId]);
                $restantes = $rstmt->fetchAll();

                $epIds = array_column($restantes, 'id');
                $txIds = array_filter(array_column($restantes, 'transacao_id'));

                if ($epIds) {
                    $ph = implode(',', array_fill(0, count($epIds), '?'));
                    $pdo->prepare(
                        "UPDATE `{$p}emprestimos_parcelas`
                         SET status='antecipado', data_pagamento=? WHERE id IN ($ph)"
                    )->execute(array_merge([$dataPag], $epIds));
                }
                if ($txIds) {
                    $ph = implode(',', array_fill(0, count($txIds), '?'));
                    $pdo->prepare("DELETE FROM `{$p}transacoes` WHERE id IN ($ph)")->execute($txIds);
                }
            } else {
                $pdo->prepare(
                    "UPDATE `{$p}emprestimos`
                     SET parcelas_pagas=?,
                         saldo_devedor = GREATEST(0, saldo_devedor - ?),
                         status = IF(?, 'quitado', status),
                         atualizado_em=NOW()
                     WHERE id=?"
                )->execute([
                    $novasPagas,
                    $parcela['valor_amortizacao'],
                    $quitado ? 1 : 0,
                    $parcela['emprestimo_id'],
                ]);
            }

            // Debita da conta
            if ($ctPag) {
                $pdo->prepare(
                    "UPDATE `{$p}contas` SET saldo_atual = saldo_atual - ? WHERE id=?"
                )->execute([$valorPago, $ctPag]);
            }

            $pdo->commit();
            if ($quitado)        $msg = 'Última parcela paga — empréstimo quitado!';
            elseif ($antecipado) $msg = 'Parcela antecipada com sucesso!';
            else                 $msg = 'Parcela registrada como paga.';
            respostaJSON(['success' => true, 'msg' => $msg, 'quitado' => $quitado, 'antecipado' => $antecipado]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            erroInterno($e);
        }
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    // Coleta transações pendentes antes do CASCADE deletar as parcelas
    $pendingIds = getPendingTransacaoIds($pdo, $p, $id);

    $pdo->prepare("DELETE FROM `{$p}emprestimos` WHERE id=?")->execute([$id]);

    // Remove as despesas futuras que não chegaram a ser pagas
    deletePendingTransacoes($pdo, $p, $pendingIds);

    respostaJSON(['success' => true, 'msg' => 'Empréstimo removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
