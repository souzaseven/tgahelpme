<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p = TABLE_PREFIX;

// Rate limit: máximo 60 buscas por minuto por sessão
$_SESSION['busca_ts']    ??= time();
$_SESSION['busca_count'] ??= 0;
if (time() - $_SESSION['busca_ts'] > 60) {
    $_SESSION['busca_ts']    = time();
    $_SESSION['busca_count'] = 0;
}
if (++$_SESSION['busca_count'] > 60) {
    http_response_code(429);
    respostaJSON(['success' => false, 'erro' => 'Muitas requisições. Aguarde um momento.']);
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    respostaJSON(['success' => true, 'resultados' => []]);
}

$like    = "%$q%";
$results = [];

// Transações (despesas + receitas)
$stmt = $pdo->prepare(
    "SELECT id, tipo, descricao, valor, data, status
     FROM `{$p}transacoes`
     WHERE descricao LIKE ? AND terceiro_id IS NULL
     ORDER BY data DESC LIMIT 6"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $r) {
    $results[] = [
        'tipo'     => 'transacao',
        'icone'    => $r['tipo'] === 'receita' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down',
        'cor'      => $r['tipo'] === 'receita' ? 'var(--emerald)' : 'var(--rose)',
        'titulo'   => $r['descricao'],
        'subtitulo'=> date('d/m/Y', strtotime($r['data'])) . ' · R$ ' . number_format($r['valor'], 2, ',', '.'),
        'link'     => '?p=' . ($r['tipo'] === 'receita' ? 'receitas' : 'despesas'),
        'id'       => $r['id'],
    ];
}

// Contas
$stmt = $pdo->prepare(
    "SELECT id, nome, tipo, saldo_atual FROM `{$p}contas` WHERE nome LIKE ? AND ativo=1 LIMIT 3"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $r) {
    $results[] = [
        'tipo'     => 'conta',
        'icone'    => 'fa-university',
        'cor'      => 'var(--indigo)',
        'titulo'   => $r['nome'],
        'subtitulo'=> 'Conta · Saldo: R$ ' . number_format($r['saldo_atual'], 2, ',', '.'),
        'link'     => '?p=contas',
        'id'       => $r['id'],
    ];
}

// Cartões
$stmt = $pdo->prepare(
    "SELECT id, nome, bandeira, limite_total FROM `{$p}cartoes` WHERE nome LIKE ? AND ativo=1 LIMIT 3"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $r) {
    $results[] = [
        'tipo'     => 'cartao',
        'icone'    => 'fa-credit-card',
        'cor'      => 'var(--indigo)',
        'titulo'   => $r['nome'],
        'subtitulo'=> 'Cartão · Limite: R$ ' . number_format($r['limite_total'], 2, ',', '.'),
        'link'     => '?p=cartoes',
        'id'       => $r['id'],
    ];
}

// Categorias
$stmt = $pdo->prepare(
    "SELECT id, nome, tipo, cor FROM `{$p}categorias` WHERE nome LIKE ? AND ativo=1 LIMIT 3"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $r) {
    $results[] = [
        'tipo'     => 'categoria',
        'icone'    => 'fa-tag',
        'cor'      => $r['cor'] ?? 'var(--text-400)',
        'titulo'   => $r['nome'],
        'subtitulo'=> 'Categoria · ' . ucfirst($r['tipo']),
        'link'     => '?p=categorias',
        'id'       => $r['id'],
    ];
}

// Metas
$stmt = $pdo->prepare(
    "SELECT id, nome, valor_alvo, valor_atual FROM `{$p}metas` WHERE nome LIKE ? LIMIT 3"
);
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $r) {
    $pct = $r['valor_alvo'] > 0 ? round($r['valor_atual'] / $r['valor_alvo'] * 100) : 0;
    $results[] = [
        'tipo'     => 'meta',
        'icone'    => 'fa-bullseye',
        'cor'      => 'var(--amber)',
        'titulo'   => $r['nome'],
        'subtitulo'=> "Meta · {$pct}% concluída",
        'link'     => '?p=metas',
        'id'       => $r['id'],
    ];
}

respostaJSON(['success' => true, 'resultados' => $results, 'total' => count($results)]);
