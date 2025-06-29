<?php
// =========================================
// BLOCO 1: CONEXÃO COM O BANCO
// =========================================
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password, $options);
} catch (PDOException $e) {
  die("Erro de conexão: " . $e->getMessage());
}

// =========================================
// BLOCO 2: FILTROS (tema, mês, ano)
// =========================================
$temaFiltro = $_GET['tema'] ?? '';
$mesFiltro = $_GET['mes'] ?? '';
$anoFiltro = $_GET['ano'] ?? '';

$temas = $pdo->query("SELECT DISTINCT tema FROM perguntas_respostas ORDER BY tema")->fetchAll(PDO::FETCH_COLUMN);

// =========================================
// BLOCO 3: CONSULTA DE PERGUNTAS MAIS ACESSADAS
// =========================================
$sql = "
  SELECT pr.id, pr.pergunta, pr.tema, pr.criado_em, COUNT(pl.id) AS acessos
  FROM perguntas_logs pl
  INNER JOIN perguntas_respostas pr ON pr.id = pl.pergunta_id
  WHERE 1 = 1
";
$params = [];

if ($temaFiltro) {
  $sql .= " AND pr.tema = :tema";
  $params[':tema'] = $temaFiltro;
}
if ($mesFiltro && $anoFiltro) {
  $sql .= " AND MONTH(pl.data_acesso) = :mes AND YEAR(pl.data_acesso) = :ano";
  $params[':mes'] = $mesFiltro;
  $params[':ano'] = $anoFiltro;
}
$sql .= " GROUP BY pr.id ORDER BY acessos DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll();

// =========================================
// BLOCO 4: EXPORTAÇÃO CSV
// =========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header("Content-Type: text/csv");
  header("Content-Disposition: attachment; filename=estatisticas.csv");

  echo "Pergunta,Tema,Acessos,Criado em\n";
  foreach ($dados as $row) {
    echo '"' . str_replace('"', '""', $row['pergunta']) . '",';
    echo '"' . $row['tema'] . '",';
    echo $row['acessos'] . ",";
    echo date('d/m/Y', strtotime($row['criado_em'])) . "\n";
  }
  exit;
}

// =========================================
// BLOCO 5: AGRUPAMENTO POR MÊS (gráfico)
// =========================================
$graficoSQL = "
  SELECT DATE_FORMAT(pl.data_acesso, '%Y-%m') AS mes, COUNT(*) AS total
  FROM perguntas_logs pl
  INNER JOIN perguntas_respostas pr ON pr.id = pl.pergunta_id
  WHERE 1 = 1
";
if ($temaFiltro) {
  $graficoSQL .= " AND pr.tema = :tema2";
  $params[':tema2'] = $temaFiltro;
}
$graficoSQL .= " GROUP BY mes ORDER BY mes";

$stmtGrafico = $pdo->prepare($graficoSQL);
$stmtGrafico->execute($params);
$graficoDados = $stmtGrafico->fetchAll();

// =========================================
// BLOCO 6: GRÁFICO DE PERGUNTAS PENDENTES POR USUÁRIO (origem)
// =========================================
$usuariosSQL = "
  SELECT origem AS usuario, COUNT(*) AS total
  FROM perguntas_pendentes
  WHERE origem IS NOT NULL AND origem != ''
  GROUP BY origem
  ORDER BY total DESC
  LIMIT 5
";
$usuarios = $pdo->query($usuariosSQL)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Estatísticas - TGAmeAjuda iA</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; max-width: 960px; margin: auto; }
    h1 { text-align: center; color: #005792; }
    form { margin-bottom: 20px; text-align: center; }
    select, button { padding: 8px; margin: 5px; }
    table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
    th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
    th { background-color: #005792; color: white; }
    canvas { margin-top: 40px; }
  </style>
</head>
<body>

<h1>📊 Estatísticas - TGAmeAjuda iA</h1>

<!-- BLOCO 7: FILTROS E EXPORTAÇÃO -->
<form method="get">
  <label>Tema:</label>
  <select name="tema">
    <option value="">-- Todos --</option>
    <?php foreach ($temas as $tema): ?>
      <option value="<?= $tema ?>" <?= $tema === $temaFiltro ? 'selected' : '' ?>><?= $tema ?></option>
    <?php endforeach; ?>
  </select>

  <label>Mês:</label>
  <select name="mes">
    <option value="">--</option>
    <?php for ($m = 1; $m <= 12; $m++): ?>
      <option value="<?= $m ?>" <?= $mesFiltro == $m ? 'selected' : '' ?>><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
    <?php endfor; ?>
  </select>

  <label>Ano:</label>
  <select name="ano">
    <option value="">--</option>
    <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
      <option value="<?= $y ?>" <?= $anoFiltro == $y ? 'selected' : '' ?>><?= $y ?></option>
    <?php endfor; ?>
  </select>

  <button type="submit">Filtrar</button>
  <a href="?<?= http_build_query($_GET) ?>&export=csv"><button type="button">📁 Exportar CSV</button></a>
</form>

<!-- BLOCO 8: GRÁFICO POR MÊS -->
<canvas id="graficoMes" height="90"></canvas>
<script>
  const ctx = document.getElementById('graficoMes').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($graficoDados, 'mes')) ?>,
      datasets: [{
        label: 'Acessos por mês',
        data: <?= json_encode(array_column($graficoDados, 'total')) ?>,
        backgroundColor: '#005792'
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>

<!-- BLOCO 9: TABELA DE PERGUNTAS MAIS ACESSADAS -->
<table>
  <thead>
    <tr>
      <th>Pergunta</th>
      <th>Tema</th>
      <th>Acessos</th>
      <th>Criado em</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($dados as $row): ?>
    <tr>
      <td><?= htmlspecialchars($row['pergunta']) ?></td>
      <td><?= htmlspecialchars($row['tema']) ?></td>
      <td><?= $row['acessos'] ?></td>
      <td><?= date('d/m/Y', strtotime($row['criado_em'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- BLOCO 10: GRÁFICO POR USUÁRIO (origem) -->
<canvas id="graficoUsuario" height="80"></canvas>
<script>
  const ctx2 = document.getElementById('graficoUsuario').getContext('2d');
  new Chart(ctx2, {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_column($usuarios, 'usuario')) ?>,
      datasets: [{
        label: 'Perguntas Pendentes por Usuário',
        data: <?= json_encode(array_column($usuarios, 'total')) ?>,
        backgroundColor: ['#005792', '#0077cc', '#0099ff', '#66ccff', '#99e6ff']
      }]
    }
  });
</script>

</body>
</html>
