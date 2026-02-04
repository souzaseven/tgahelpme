<?php
require_once __DIR__ . '/conexao.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';

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

/* 🔥 NOVOS */
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

if (!in_array($ordenar_por, $camposPermitidos)) {
  $ordenar_por = 'criado_em';
}

if (!in_array($ordenar_dir, ['ASC', 'DESC'])) {
  $ordenar_dir = 'DESC';
}

if ($agrupar_por && !in_array($agrupar_por, $camposPermitidos)) {
  $agrupar_por = '';
}

/* =====================================================
   VALIDAÇÕES
===================================================== */
if ($data_ini && $data_fim && $data_ini > $data_fim) {
  exit('Data inicial não pode ser maior que a final.');
}

if ($status && !in_array($status, ['ATIVO','INATIVO'])) {
  exit('Status inválido.');
}

/* =====================================================
   WHERE DINÂMICO
===================================================== */
$where  = [];
$params = [];

if ($codigo_ini !== '') {
  $where[] = 'codigo_cliente >= ?';
  $params[] = $codigo_ini;
}

if ($codigo_fim !== '') {
  $where[] = 'codigo_cliente <= ?';
  $params[] = $codigo_fim;
}

if ($nome_cliente !== '') {
  $where[] = 'nome_cliente LIKE ?';
  $params[] = "%{$nome_cliente}%";
}

if ($versao !== '') {
  $where[] = 'versao_padrao = ?';
  $params[] = $versao;
}

if ($status !== '') {
  $where[] = 'status = ?';
  $params[] = $status;
}

if ($data_ini && $data_fim) {
  $where[] = 'DATE(criado_em) BETWEEN ? AND ?';
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

/* WHERE */
if ($where) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

/* GROUP BY */
if ($agrupar_por) {
  $sql .= " GROUP BY {$agrupar_por}";
}

/* ORDER BY */
$sql .= " ORDER BY {$ordenar_por} {$ordenar_dir}";

/* =====================================================
   EXECUÇÃO
===================================================== */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   EXCEL
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
            <td>{$r['codigo_cliente']}</td>
            <td>{$r['nome_cliente']}</td>
            <td>{$r['versao_padrao']}</td>
            <td>{$r['status']}</td>
            <td>{$r['criado_em']}</td>
          </tr>";
  }
  echo "</table>";
  exit;
}

/* =====================================================
   HTML / PDF
===================================================== */
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Relatório de Logins</title>
<style>
body{font-family:Arial;font-size:12px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #333;padding:6px}
th{background:#eee}
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
  <td><?= $r['codigo_cliente'] ?></td>
  <td><?= $r['nome_cliente'] ?></td>
  <td><?= $r['versao_padrao'] ?></td>
  <td><?= $r['status'] ?></td>
  <td><?= date('d/m/Y', strtotime($r['criado_em'])) ?></td>
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
  $dompdf->stream('relatorio_logins.pdf', ['Attachment'=>false]);
  exit;
}

echo $html;
