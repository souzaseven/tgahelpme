<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── Verifica se deve gerar lançamento em determinado mês ──────
function deveGerarNoMes(array $r, int $mes, int $ano): bool
{
    $dtInicio = new DateTime(substr($r['data_inicio'], 0, 7) . '-01');
    $dtAlvo   = new DateTime("$ano-$mes-01");

    if ($dtAlvo < $dtInicio) return false;

    if (!empty($r['data_fim'])) {
        $dtFim = new DateTime(substr($r['data_fim'], 0, 7) . '-01');
        if ($dtAlvo > $dtFim) return false;
    }

    $diffMeses = ($dtAlvo->format('Y') - $dtInicio->format('Y')) * 12
               + ((int)$dtAlvo->format('m') - (int)$dtInicio->format('m'));

    $periodo = [
        'diaria'     => 0,
        'semanal'    => 0,
        'quinzenal'  => 0,
        'mensal'     => 1,
        'bimestral'  => 2,
        'trimestral' => 3,
        'semestral'  => 6,
        'anual'      => 12,
    ][$r['frequencia']] ?? 1;

    return $periodo === 0 || ($diffMeses % $periodo === 0);
}

// ── Gera um lançamento para um mês (sem duplicar) ─────────────
function gerarParaMes(PDO $pdo, string $p, array $rec, int $mes, int $ano): bool
{
    if (!deveGerarNoMes($rec, $mes, $ano)) return false;

    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM `{$p}transacoes`
         WHERE recorrencia_id=? AND MONTH(data)=? AND YEAR(data)=?"
    );
    $check->execute([$rec['id'], $mes, $ano]);
    if ((int)$check->fetchColumn() > 0) return false;

    $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $dia  = min((int)$rec['dia_vencimento'], $diasNoMes);
    $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

    $pdo->prepare(
        "INSERT INTO `{$p}transacoes`
         (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id, recorrencia_id, status)
         VALUES (?,?,?,?,?,?,?,?,'pendente')"
    )->execute([
        $rec['tipo'], $rec['descricao'], $rec['valor'], $data,
        $rec['categoria_id'], $rec['conta_id'], $rec['cartao_id'], $rec['id'],
    ]);
    return true;
}

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $mes    = (int)($_GET['mes']    ?? date('m'));
    $ano    = (int)($_GET['ano']    ?? date('Y'));
    $tipo   = trim($_GET['tipo']    ?? '');
    $status = trim($_GET['status']  ?? 'ativo');

    $where  = ['1=1'];
    $params = [$mes, $ano];

    if ($tipo !== '')              { $where[] = 'r.tipo = ?';  $params[] = $tipo; }
    if ($status === 'ativo')       { $where[] = 'r.ativo = 1'; }
    elseif ($status === 'inativo') { $where[] = 'r.ativo = 0'; }

    $wSql = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT r.*,
                c.nome cat_nome, c.cor cat_cor,
                ct.nome conta_nome,
                cc.nome cartao_nome,
                (SELECT COUNT(*) FROM `{$p}transacoes` t
                 WHERE t.recorrencia_id = r.id
                   AND MONTH(t.data) = ? AND YEAR(t.data) = ?) gerado_mes
         FROM `{$p}recorrencias` r
         LEFT JOIN `{$p}categorias` c  ON c.id = r.categoria_id
         LEFT JOIN `{$p}contas`     ct ON ct.id = r.conta_id
         LEFT JOIN `{$p}cartoes`    cc ON cc.id = r.cartao_id
         WHERE $wSql
         ORDER BY r.tipo DESC, r.dia_vencimento, r.descricao"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    respostaJSON(['success' => true, 'dados' => $rows]);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Salvar (criar / atualizar) ─────────────────────────────
    if ($acao === 'salvar') {
        $id        = (int)($d['id']         ?? 0);
        $descricao = trim($d['descricao']   ?? '');
        $tipo      = in_array($d['tipo'] ?? '', ['receita','despesa']) ? $d['tipo'] : 'despesa';
        $valor     = round(abs((float)($d['valor'] ?? 0)), 2);
        $dataIni   = $d['data_inicio']      ?? '';

        if (!$descricao || !$valor || !$dataIni) {
            respostaJSON(['success' => false, 'erro' => 'Descrição, valor e data de início são obrigatórios.']);
        }

        $freqs = ['diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual'];
        $freq     = in_array($d['frequencia'] ?? '', $freqs) ? $d['frequencia'] : 'mensal';
        $diaVenc  = max(1, min(31, (int)($d['dia_vencimento'] ?? 1)));
        $dataFim  = !empty($d['data_fim'])  ? $d['data_fim']  : null;
        $catId    = (int)($d['categoria_id']?? 0) ?: null;
        $contaId  = (int)($d['conta_id']    ?? 0) ?: null;
        $cartaoId = (int)($d['cartao_id']   ?? 0) ?: null;

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}recorrencias`
                 SET descricao=?, tipo=?, valor=?, frequencia=?, dia_vencimento=?,
                     data_inicio=?, data_fim=?, categoria_id=?, conta_id=?, cartao_id=?
                 WHERE id=?"
            )->execute([$descricao, $tipo, $valor, $freq, $diaVenc,
                        $dataIni, $dataFim, $catId, $contaId, $cartaoId, $id]);
            respostaJSON(['success' => true, 'msg' => 'Recorrência atualizada.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}recorrencias`
             (descricao, tipo, valor, frequencia, dia_vencimento, data_inicio, data_fim,
              categoria_id, conta_id, cartao_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$descricao, $tipo, $valor, $freq, $diaVenc,
                    $dataIni, $dataFim, $catId, $contaId, $cartaoId]);

        // Auto-gera lançamento para o mês atual e próximo mês, se aplicável
        $novoId  = (int)$pdo->lastInsertId();
        $sNovaRec = $pdo->prepare("SELECT * FROM `{$p}recorrencias` WHERE id=?");
        $sNovaRec->execute([$novoId]);
        $novaRec = $sNovaRec->fetch();

        $mesCurr  = (int)date('m');
        $anoCurr  = (int)date('Y');
        $mesNext  = $mesCurr === 12 ? 1  : $mesCurr + 1;
        $anoNext  = $mesCurr === 12 ? $anoCurr + 1 : $anoCurr;

        $geradosCurr = gerarParaMes($pdo, $p, $novaRec, $mesCurr, $anoCurr) ? 1 : 0;
        $geradosNext = gerarParaMes($pdo, $p, $novaRec, $mesNext, $anoNext) ? 1 : 0;
        $totalGerados = $geradosCurr + $geradosNext;

        $msgExtra = $totalGerados > 0
            ? " $totalGerados lançamento(s) gerado(s) automaticamente."
            : '';
        respostaJSON(['success' => true, 'msg' => 'Conta fixa cadastrada com sucesso.' . $msgExtra, 'gerados' => $totalGerados]);
    }

    // ── Gerar lançamentos ──────────────────────────────────────
    if ($acao === 'gerar') {
        $mes    = (int)($d['mes'] ?? date('m'));
        $ano    = (int)($d['ano'] ?? date('Y'));
        $soId   = (int)($d['id'] ?? 0);

        if ($soId > 0) {
            $s = $pdo->prepare("SELECT * FROM `{$p}recorrencias` WHERE id=? AND ativo=1");
            $s->execute([$soId]);
            $recs = [$s->fetch()];
            $recs = array_filter($recs);
        } else {
            $recs = $pdo->query("SELECT * FROM `{$p}recorrencias` WHERE ativo=1")->fetchAll();
        }

        $gerados   = 0;
        $ignorados = 0;

        foreach ($recs as $rec) {
            if (gerarParaMes($pdo, $p, $rec, $mes, $ano)) $gerados++;
            else $ignorados++;
        }

        $msg = $gerados > 0
            ? "$gerados lançamento(s) gerado(s) com sucesso."
            : 'Nenhum lançamento novo — todos já foram gerados neste mês.';
        respostaJSON(['success' => true, 'gerados' => $gerados, 'ignorados' => $ignorados, 'msg' => $msg]);
    }

    // ── Alternar ativo / inativo ───────────────────────────────
    if ($acao === 'toggle') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

        $pdo->prepare(
            "UPDATE `{$p}recorrencias` SET ativo = 1 - ativo WHERE id=?"
        )->execute([$id]);
        respostaJSON(['success' => true, 'msg' => 'Status alterado.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE: excluir ───────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);

    $pdo->prepare("DELETE FROM `{$p}recorrencias` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Conta fixa removida.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
