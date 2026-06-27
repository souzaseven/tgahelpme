<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── Auto-migração: adiciona coluna titular se não existir ─────
$col = $pdo->query("SHOW COLUMNS FROM `{$p}contas` LIKE 'titular'")->fetch();
if (!$col) {
    $pdo->exec(
        "ALTER TABLE `{$p}contas`
         ADD COLUMN `titular` VARCHAR(100) DEFAULT NULL AFTER `icone`"
    );
}

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {

    // ?impacto=1&id=X → retorna qtd de transações vinculadas à conta
    if (!empty($_GET['impacto']) && !empty($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$p}transacoes` WHERE conta_id=?");
        $stmt->execute([$id]);
        respostaJSON(['success' => true, 'qtd_transacoes' => (int)$stmt->fetchColumn()]);
    }

    $contas = $pdo->query(
        "SELECT * FROM `{$p}contas` WHERE ativo=1 ORDER BY titular, nome"
    )->fetchAll();

    // Agrupa por titular para o painel de comparação
    $grupos = [];
    foreach ($contas as $c) {
        $t = trim($c['titular'] ?? '') ?: 'Sem titular';
        $grupos[$t][] = $c;
    }

    // Totais por titular
    $totaisPorTitular = [];
    foreach ($grupos as $titular => $conts) {
        $totaisPorTitular[$titular] = [
            'saldo'  => array_sum(array_map(fn($c) => $c['incluir_total'] ? (float)$c['saldo_atual'] : 0, $conts)),
            'qtd'    => count($conts),
        ];
    }

    $saldoTotal = array_sum(array_column($contas, 'saldo_atual'));

    respostaJSON([
        'success'          => true,
        'contas'           => $contas,
        'grupos'           => $grupos,
        'totais_titular'   => $totaisPorTitular,
        'saldo_total'      => $saldoTotal,
    ]);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    if ($acao === 'salvar') {
        $id      = (int)($d['id']   ?? 0);
        $nome    = trim($d['nome']  ?? '');
        if (!$nome) respostaJSON(['success' => false, 'erro' => 'Nome é obrigatório.']);

        $tipos   = ['corrente','poupanca','carteira','investimento','outro'];
        $tipo    = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'corrente';
        $banco   = trim($d['banco']           ?? '');
        $saldoIni= round((float)($d['saldo_inicial'] ?? 0), 2);
        $cor     = preg_match('/^#[0-9a-fA-F]{6}$/', $d['cor'] ?? '') ? $d['cor'] : '#6366f1';
        $icone   = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($d['icone'] ?? ''))) ?: 'university';
        $titular = trim($d['titular']         ?? '');
        $incTotal= !empty($d['incluir_total']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}contas`
                 SET nome=?, tipo=?, banco=?, cor=?, icone=?, titular=?,
                     incluir_total=?, atualizado_em=NOW()
                 WHERE id=?"
            )->execute([$nome, $tipo, $banco, $cor, $icone, $titular ?: null, $incTotal, $id]);
            respostaJSON(['success' => true, 'msg' => 'Conta atualizada.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}contas`
             (nome, tipo, banco, saldo_inicial, saldo_atual, cor, icone, titular, incluir_total)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([$nome, $tipo, $banco, $saldoIni, $saldoIni, $cor, $icone, $titular ?: null, $incTotal]);
        respostaJSON(['success' => true, 'msg' => 'Conta criada com sucesso.']);
    }

    // Ajuste de saldo (correção manual)
    if ($acao === 'ajustar_saldo') {
        $id    = (int)($d['id']          ?? 0);
        $saldo = round((float)($d['saldo'] ?? 0), 2);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $pdo->prepare(
            "UPDATE `{$p}contas` SET saldo_atual=?, atualizado_em=NOW() WHERE id=?"
        )->execute([$saldo, $id]);
        respostaJSON(['success' => true, 'msg' => 'Saldo ajustado.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE: desativar conta ───────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
    $pdo->prepare(
        "UPDATE `{$p}contas` SET ativo=0, atualizado_em=NOW() WHERE id=?"
    )->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Conta desativada.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
