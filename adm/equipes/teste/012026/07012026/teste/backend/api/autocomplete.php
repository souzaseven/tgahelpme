<?php
// ============================================================
// autocomplete.php — Sugestões de descrição ao digitar um novo
// lançamento (ver initAutocomplete() em app.js). Sugere descrições
// já usadas antes pelo próprio usuário, mais frequentes primeiro.
//
// GET ?q=texto&tipo=despesa|receita
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p    = TABLE_PREFIX;
$q    = trim($_GET['q']    ?? '');
$tipo = trim($_GET['tipo'] ?? '');

if (strlen($q) < 2 || !in_array($tipo, ['despesa', 'receita'])) {
    respostaJSON(['success' => true, 'dados' => []]);
}

$stmt = $pdo->prepare(
    "SELECT descricao, COUNT(*) freq
     FROM `{$p}transacoes`
     WHERE tipo = ?
       AND descricao LIKE ?
       AND status != 'cancelado'
     GROUP BY descricao
     ORDER BY freq DESC, descricao
     LIMIT 8"
);
$stmt->execute([$tipo, $q . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Se poucos resultados com prefixo exato, busca por "contém"
if (count($rows) < 3) {
    $stmt->execute([$tipo, '%' . $q . '%']);
    $extra = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $rows  = array_values(array_unique(array_merge($rows, $extra)));
    $rows  = array_slice($rows, 0, 8);
}

respostaJSON(['success' => true, 'dados' => $rows]);
