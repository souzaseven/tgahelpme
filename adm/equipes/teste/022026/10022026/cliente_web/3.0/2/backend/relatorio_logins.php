<?php
/**
 * backend/relatorio_logins.php — TGA Clientes Web
 *
 * O que adicionei em relação ao original:
 *
 *  1. Autenticação via verifica_acesso.php — qualquer requisição sem sessão
 *     válida é redirecionada para login.php antes de executar qualquer linha
 *     de código de negócio. Antes, qualquer pessoa com a URL acessava.
 *
 *  2. Proteção XSS nos outputs HTML e Excel — todos os valores vindos do
 *     banco passam por htmlspecialchars() antes de serem impressos.
 *     Sem isso, um nome de cliente com <script> ou <img onerror> executava
 *     no navegador de quem abria o relatório.
 *
 *  3. Função e() centralizada — atalho para htmlspecialchars() com UTF-8,
 *     evita repetição e garante consistência em todo o arquivo.
 *
 * A lógica original (filtros, ORDER BY, GROUP BY, PDF, Excel, HTML)
 * está 100% preservada.
 */

// ── 1. Autenticação ────────────────────────────────────────────────────────
// verifica_acesso.php já chama session_start(), checa admweb e timeout de 2h.
// Se a sessão for inválida, ele redireciona e encerra — nunca chega abaixo.
require_once __DIR__ . '/verifica_acesso.php';
require_once __DIR__ . '/conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';

// ── 2. Helper XSS ──────────────────────────────────────────────────────────
// Uso uma função curta para não poluir o código com htmlspecialchars() longo.
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* =====================================================
   INPUT
===================================================== */
$codigo_ini   = trim($_GET['codigo_cliente_ini'] ?? '');
$codigo_fim   = trim($_GET['codigo_cliente_fim'] ?? '');
$nome_cliente = trim($_GET['nome_cliente'] ?? '');
$versao       = trim($_GET['versao_padrao'] ?? '');
$status       = trim($_GET['status'] ?? '');
$data_ini     = trim($_GET['data_inicio'] ?? '');
$data_fim     = trim($_GET['data_fim'] ?? '');
$formato      = trim($_GET['formato'] ?? 'html');

$ordenar_por  = trim($_GET['ordenar_por'] ?? 'criado_em');
$ordenar_dir  = strtoupper(trim($_GET['ordenar_dir'] ?? 'DESC'));
$agrupar_por  = trim($_GET['agrupar_por'] ?? '');

/* =====================================================
   SEGURANÇA — CAMPOS PERMITIDOS
===================================================== */
$camposPermitidos = [
    'codigo_cliente',
    'nome_cliente',
    'versao_padrao',
    'status',
    'criado_em',
    'atualizado_em'
];

if (!in_array($ordenar_por, $camposPermitidos, true)) {
    $ordenar_por = 'criado_em';
}

if (!in_array($ordenar_dir, ['ASC', 'DESC'], true)) {
    $ordenar_dir = 'DESC';
}

if ($agrupar_por && !in_array($agrupar_por, $camposPermitidos, true)) {
    $agrupar_por = '';
}

/* =====================================================
   VALIDAÇÕES
===================================================== */
if ($data_ini && $data_fim && $data_ini > $data_fim) {
    exit('Data inicial não pode ser maior que a final.');
}

if ($status && !in_array($status, ['ATIVO', 'INATIVO'], true)) {
    exit('Status inválido.');
}

/* =====================================================
   WHERE DINÂMICO
===================================================== */
$where  = [];
$params = [];

if ($codigo_ini !== '') {
    $where[]  = 'codigo_cliente >= ?';
    $params[] = $codigo_ini;
}

if ($codigo_fim !== '') {
    $where[]  = 'codigo_cliente <= ?';
    $params[] = $codigo_fim;
}

if ($nome_cliente !== '') {
    $where[]  = 'nome_cliente LIKE ?';
    $params[] = "%{$nome_cliente}%";
}

if ($versao !== '') {
    $where[]  = 'versao_padrao = ?';
    $params[] = $versao;
}

if ($status !== '') {
    $where[]  = 'status = ?';
    $params[] = $status;
}

if ($data_ini && $data_fim) {
    $where[]  = 'DATE(criado_em) BETWEEN ? AND ?';
    $params[] = $data_ini;
    $params[] = $data_fim;
}

/* =====================================================
   SQL BASE
===================================================== */
$sql = "
    SELECT
        id,
        codigo_cliente,
        nome_cliente,
        caminho_acesso,
        versao_padrao,
        status,
        criado_em,
        atualizado_em
    FROM clientes_web_login
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

if ($agrupar_por) {
    $sql .= " GROUP BY {$agrupar_por}";
}

$sql .= " ORDER BY {$ordenar_por} {$ordenar_dir}";

/* =====================================================
   EXECUÇÃO
===================================================== */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   EXCEL
   Todos os valores passam por e() para evitar XSS
   caso o arquivo seja aberto num visualizador HTML.
===================================================== */
if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=relatorio_logins.xls');

    echo "<table border='1'>";
    echo "<tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Versão</th>
            <th>Status</th>
            <th>Criado em</th>
          </tr>";

    foreach ($rows as $r) {
        echo "<tr>
                <td>" . e($r['codigo_cliente']) . "</td>
                <td>" . e($r['nome_cliente'])   . "</td>
                <td>" . e($r['versao_padrao'])   . "</td>
                <td>" . e($r['status'])           . "</td>
                <td>" . e($r['criado_em'])        . "</td>
              </tr>";
    }

    echo "</table>";
    exit;
}

/* =====================================================
   HTML / PDF
   Mesmos valores protegidos com e().
===================================================== */
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Relatório de Logins</title>
  <style>
    body  { font-family: Arial; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 6px; }
    th { background: #eee; }
  </style>
</head>
<body>

<h2>📊 Relatório de Logins Web</h2>

<table>
  <tr>
    <th>Código</th>
    <th>Cliente</th>
    <th>Versão</th>
    <th>Status</th>
    <th>Criado em</th>
  </tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['codigo_cliente']) ?></td>
    <td><?= e($r['nome_cliente'])   ?></td>
    <td><?= e($r['versao_padrao'])  ?></td>
    <td><?= e($r['status'])          ?></td>
    <td><?= e(date('d/m/Y', strtotime($r['criado_em']))) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
<?php
$html = ob_get_clean();

/* PDF */
if ($formato === 'pdf') {
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream('relatorio_logins.pdf', ['Attachment' => false]);
    exit;
}

echo $html;