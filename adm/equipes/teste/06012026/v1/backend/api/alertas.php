<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao = trim($_GET['acao'] ?? 'listar');

    if ($acao === 'listar') {
        $nivel = trim($_GET['nivel'] ?? '');
        $lido  = $_GET['lido']  ?? '';
        $tipo  = trim($_GET['tipo']  ?? '');

        $where  = [];
        $params = [];

        if ($nivel) { $where[] = 'nivel=?';  $params[] = $nivel; }
        if ($tipo)  { $where[] = 'tipo=?';   $params[] = $tipo;  }
        if ($lido !== '') { $where[] = 'lido=?'; $params[] = (int)$lido; }

        $sql  = "SELECT * FROM `{$p}alertas`"
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
              . " ORDER BY lido ASC, nivel DESC, criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll();

        $kpi = [
            'total'    => count($dados),
            'nao_lido' => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE lido=0")->fetchColumn(),
            'urgente'  => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='urgente' AND lido=0")->fetchColumn(),
            'aviso'    => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='aviso'   AND lido=0")->fetchColumn(),
            'info'     => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='info'    AND lido=0")->fetchColumn(),
        ];
        respostaJSON(['success' => true, 'dados' => $dados, 'kpi' => $kpi]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Salvar (criar / editar) ────────────────────────────────
    if ($acao === 'salvar') {
        $id      = (int)($d['id'] ?? 0);
        $titulo  = trim($d['titulo']  ?? '');
        if (!$titulo) respostaJSON(['success' => false, 'erro' => 'Título é obrigatório.']);

        $tipos_ok  = ['vencimento','limite_cartao','orcamento','meta','emprestimo','saldo','custom'];
        $niveis_ok = ['info','aviso','urgente'];
        $tipo      = in_array($d['tipo']  ?? '', $tipos_ok)  ? $d['tipo']  : 'custom';
        $nivel     = in_array($d['nivel'] ?? '', $niveis_ok) ? $d['nivel'] : 'aviso';
        $mensagem  = trim($d['mensagem']   ?? '');
        $data_al   = !empty($d['data_alerta']) ? $d['data_alerta'] : null;

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}alertas` SET tipo=?, titulo=?, mensagem=?, nivel=?, data_alerta=? WHERE id=?"
            )->execute([$tipo, $titulo, $mensagem, $nivel, $data_al, $id]);
            respostaJSON(['success' => true, 'msg' => 'Alerta atualizado.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}alertas` (tipo, titulo, mensagem, nivel, data_alerta) VALUES (?,?,?,?,?)"
        )->execute([$tipo, $titulo, $mensagem, $nivel, $data_al]);
        respostaJSON(['success' => true, 'msg' => 'Alerta criado.']);
    }

    // ── Marcar como lido ──────────────────────────────────────
    if ($acao === 'marcar_lido') {
        $id = (int)($d['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE `{$p}alertas` SET lido=1 WHERE id=?")->execute([$id]);
        } else {
            $pdo->exec("UPDATE `{$p}alertas` SET lido=1 WHERE lido=0");
        }
        respostaJSON(['success' => true, 'msg' => 'Marcado(s) como lido(s).']);
    }

    // ── Gerar alertas automáticos ─────────────────────────────
    if ($acao === 'gerar') {
        $gerados = gerarAlertasAuto($pdo, $p);
        respostaJSON(['success' => true, 'msg' => "$gerados alerta(s) gerado(s).", 'gerados' => $gerados]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
    $pdo->prepare("DELETE FROM `{$p}alertas` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Alerta removido.']);
}

// ── Geração automática ────────────────────────────────────────
function gerarAlertasAuto(PDO $pdo, string $p): int
{
    $hoje    = new DateTime();
    $gerados = 0;

    // Faturas de cartão — vencendo em até 7 dias
    $cartoes = $pdo->query("SELECT * FROM `{$p}cartoes` WHERE ativo=1")->fetchAll();
    foreach ($cartoes as $cc) {
        $dia = (int)$cc['dia_vencimento'];
        if (!$dia) continue;

        $dtVenc = DateTime::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-%02d', (int)$hoje->format('Y'), (int)$hoje->format('m'), $dia)
        );
        if (!$dtVenc) continue;
        if ($dtVenc < $hoje) $dtVenc->modify('+1 month');

        $diff = (int)$hoje->diff($dtVenc)->days;
        if ($diff > 7) continue;

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$p}alertas`
             WHERE referencia_tipo='cartao' AND referencia_id=?
               AND DATE(criado_em)=CURDATE() AND lido=0"
        );
        $check->execute([$cc['id']]);
        if ((int)$check->fetchColumn() > 0) continue;

        $nivel  = $diff <= 3 ? 'urgente' : 'aviso';
        $titulo = "Fatura {$cc['nome']} vence em {$diff} dia" . ($diff === 1 ? '' : 's');
        $pdo->prepare(
            "INSERT INTO `{$p}alertas`
             (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            'vencimento', $titulo,
            'Limite usado: R$ ' . number_format((float)$cc['limite_usado'], 2, ',', '.'),
            $nivel, 'cartao', $cc['id'], $dtVenc->format('Y-m-d'),
        ]);
        $gerados++;
    }

    // Parcelas de empréstimo — vencendo em até 7 dias
    try {
        $parcelas = $pdo->query(
            "SELECT ep.*, e.descricao AS emp_nome
             FROM `{$p}emprestimos_parcelas` ep
             JOIN `{$p}emprestimos` e ON e.id = ep.emprestimo_id
             WHERE ep.status='pendente'
               AND ep.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        )->fetchAll();
        foreach ($parcelas as $parc) {
            $dtVenc = new DateTime($parc['data_vencimento']);
            $diff   = (int)$hoje->diff($dtVenc)->days;

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$p}alertas`
                 WHERE referencia_tipo='parcela' AND referencia_id=?
                   AND DATE(criado_em)=CURDATE() AND lido=0"
            );
            $check->execute([$parc['id']]);
            if ((int)$check->fetchColumn() > 0) continue;

            $nivel  = $diff <= 3 ? 'urgente' : 'aviso';
            $titulo = "Parcela de \"{$parc['emp_nome']}\" vence em {$diff} dia" . ($diff === 1 ? '' : 's');
            $pdo->prepare(
                "INSERT INTO `{$p}alertas`
                 (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                'emprestimo', $titulo,
                'R$ ' . number_format((float)$parc['valor'], 2, ',', '.'),
                $nivel, 'parcela', $parc['id'], $parc['data_vencimento'],
            ]);
            $gerados++;
        }
    } catch (PDOException $e) { /* tabela pode não existir ainda */ }

    // Metas com prazo em 30 dias e progresso < 80%
    try {
        $metas = $pdo->query(
            "SELECT * FROM `{$p}metas`
             WHERE status='ativa'
               AND data_prazo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )->fetchAll();
        foreach ($metas as $meta) {
            $pct = $meta['valor_alvo'] > 0
                ? ($meta['valor_atual'] / $meta['valor_alvo']) * 100
                : 100;
            if ($pct >= 80) continue;

            $dtVenc = new DateTime($meta['data_prazo']);
            $diff   = (int)$hoje->diff($dtVenc)->days;

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$p}alertas`
                 WHERE referencia_tipo='meta' AND referencia_id=?
                   AND DATE(criado_em)=CURDATE() AND lido=0"
            );
            $check->execute([$meta['id']]);
            if ((int)$check->fetchColumn() > 0) continue;

            $nivel  = $diff <= 7 ? 'urgente' : 'aviso';
            $titulo = "Meta \"{$meta['nome']}\" vence em {$diff} dia" . ($diff === 1 ? '' : 's');
            $pdo->prepare(
                "INSERT INTO `{$p}alertas`
                 (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                'meta', $titulo,
                number_format($pct, 1, ',', '.') . '% concluído — meta: R$ ' . number_format((float)$meta['valor_alvo'], 2, ',', '.'),
                $nivel, 'meta', $meta['id'], $meta['data_prazo'],
            ]);
            $gerados++;
        }
    } catch (PDOException $e) { /* tabela pode não existir ainda */ }

    return $gerados;
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
