<?php
// ============================================================
// metas.php — Metas financeiras (ex: reserva de emergência, viagem)
// com aportes registrados em metas_aportes. Cada aporte soma em
// valor_atual e, se atingir o valor_alvo, a meta muda sozinha pra
// 'concluida' (ver acao=aporte).
//
// GET  ?acao=lista            — metas (ativas por padrão) + totais agregados
// GET  ?acao=aportes&id=X     — histórico de aportes de uma meta
// POST acao=salvar            — cria/edita a meta
// POST acao=aporte            — registra um aporte e atualiza o progresso
// DELETE ?id=X
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../helpers/normalizacao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao   = trim($_GET['acao']   ?? 'lista');
    $status = trim($_GET['status'] ?? '');

    if ($acao === 'lista') {
        $statusValidos = ['ativa', 'concluida', 'cancelada', 'pausada'];
        if ($status && !in_array($status, $statusValidos)) $status = '';

        $whereClause = $status ? 'WHERE status = ?' : "WHERE status NOT IN ('cancelada')";
        $stmt = $pdo->prepare("SELECT * FROM `{$p}metas` $whereClause ORDER BY prioridade, data_prazo, nome");
        $stmt->execute($status ? [$status] : []);
        $metas = $stmt->fetchAll();

        $ativas     = array_filter($metas, fn($m) => $m['status'] === 'ativa');
        $concluidas = array_filter($metas, fn($m) => $m['status'] === 'concluida');

        respostaJSON([
            'success'  => true,
            'dados'    => array_values($metas),
            'totais'   => [
                'alvo'       => array_sum(array_column(iterator_to_array($ativas, false), 'valor_alvo')),
                'atual'      => array_sum(array_column(iterator_to_array($ativas, false), 'valor_atual')),
                'ativas'     => count($ativas),
                'concluidas' => count($concluidas),
            ],
        ]);
    }

    if ($acao === 'aportes') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $stmt = $pdo->prepare(
            "SELECT * FROM `{$p}metas_aportes` WHERE meta_id=? ORDER BY data DESC, id DESC"
        );
        $stmt->execute([$id]);
        respostaJSON(['success' => true, 'aportes' => $stmt->fetchAll()]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Criar / atualizar meta ─────────────────────────────────
    if ($acao === 'salvar') {
        $id         = (int)($d['id']        ?? 0);
        $nome       = trim($d['nome']       ?? '');
        $valorAlvo  = round(abs((float)($d['valor_alvo'] ?? 0)), 2);
        $dataInicio = $d['data_inicio']     ?? date('Y-m-d');

        if (!$nome || !$valorAlvo) {
            respostaJSON(['success' => false, 'erro' => 'Nome e valor alvo são obrigatórios.']);
        }

        $tipos      = ['emergencia','carro','imovel','viagem','aposentadoria','educacao','quitar_divida','outro'];
        $tipo       = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'outro';
        $descricao  = trim($d['descricao']      ?? '');
        $aporteMens = !empty($d['aporte_mensal']) ? round(abs((float)$d['aporte_mensal']), 2) : null;
        $dataPrazo  = !empty($d['data_prazo'])   ? $d['data_prazo'] : null;
        $prioridade = max(1, min(3, (int)($d['prioridade'] ?? 3)));
        $cor        = corOuPadrao($d['cor'] ?? null, '#10b981');
        $icone      = iconeOuPadrao($d['icone'] ?? null, 'bullseye');
        $status     = in_array($d['status'] ?? '', ['ativa','pausada','concluida','cancelada'])
                      ? $d['status'] : 'ativa';

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}metas`
                 SET nome=?, descricao=?, tipo=?, valor_alvo=?, aporte_mensal=?,
                     data_inicio=?, data_prazo=?, prioridade=?, cor=?, icone=?,
                     status=?, atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$nome, $descricao, $tipo, $valorAlvo, $aporteMens,
                        $dataInicio, $dataPrazo, $prioridade, $cor, $icone, $status, $id]);
            respostaJSON(['success' => true, 'msg' => 'Meta atualizada.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}metas`
             (nome, descricao, tipo, valor_alvo, aporte_mensal, data_inicio, data_prazo,
              prioridade, cor, icone, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$nome, $descricao, $tipo, $valorAlvo, $aporteMens,
                    $dataInicio, $dataPrazo, $prioridade, $cor, $icone, $status]);
        respostaJSON(['success' => true, 'msg' => 'Meta criada com sucesso.']);
    }

    // ── Registrar aporte ───────────────────────────────────────
    if ($acao === 'aporte') {
        $metaId = (int)($d['meta_id'] ?? 0);
        $valor  = round(abs((float)($d['valor'] ?? 0)), 2);
        $data   = $d['data']     ?? date('Y-m-d');
        $desc   = trim($d['descricao'] ?? '');

        if (!$metaId || !$valor) {
            respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO `{$p}metas_aportes` (meta_id, valor, data, descricao)
                 VALUES (?,?,?,?)"
            )->execute([$metaId, $valor, $data, $desc]);

            $pdo->prepare(
                "UPDATE `{$p}metas`
                 SET valor_atual = valor_atual + ?,
                     status = IF(valor_atual + ? >= valor_alvo, 'concluida', status),
                     atualizado_em = NOW()
                 WHERE id=?"
            )->execute([$valor, $valor, $metaId]);

            $pdo->commit();

            // Verifica se foi concluída
            $meta = $pdo->prepare("SELECT status FROM `{$p}metas` WHERE id=?");
            $meta->execute([$metaId]);
            $row  = $meta->fetch();
            $msg  = $row['status'] === 'concluida' ? '🎉 Meta concluída! Parabéns!' : 'Aporte registrado com sucesso.';
            respostaJSON(['success' => true, 'msg' => $msg, 'concluida' => $row['status'] === 'concluida']);
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
    $pdo->prepare("DELETE FROM `{$p}metas` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Meta removida.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
