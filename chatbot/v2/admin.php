<?php
// ============================
// CONEXÃO COM O BANCO
// ============================
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
  die("Erro ao conectar: " . $e->getMessage());
}

// ============================
// RESUMO DO BANCO
// ============================
$total_pendentes = (int) $pdo->query("SELECT COUNT(*) FROM perguntas_pendentes")->fetchColumn();
$total_ia = (int) $pdo->query("SELECT COUNT(*) FROM perguntas_respostas WHERE fonte = 'DeepSeek'")->fetchColumn();
$total_respostas = (int) $pdo->query("SELECT COUNT(*) FROM perguntas_respostas")->fetchColumn();

// ============================
// SALVA RESPOSTA
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder'])) {
  $id = $_POST['id'];
  $pergunta = $_POST['pergunta'];
  $resposta = $_POST['resposta'];
  $tema = $_POST['tema'];
  $fonte = $_POST['fonte'];

  $stmt = $pdo->prepare("INSERT INTO perguntas_respostas (pergunta, resposta, tema, fonte) VALUES (?, ?, ?, ?)");
  $stmt->execute([$pergunta, $resposta, $tema, $fonte]);
  $pdo->prepare("DELETE FROM perguntas_pendentes WHERE id = ?")->execute([$id]);

  header("Location: admin.php?ok=1");
  exit;
}

// ============================
// BUSCA PERGUNTAS PENDENTES
// ============================
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
$pendentes = $pdo->query("SELECT * FROM perguntas_pendentes ORDER BY registrado_em DESC LIMIT $limite")->fetchAll();

// ============================
// FUNÇÃO PARA LINK AUTOMÁTICO
// ============================
function autoLink($text) {
  return preg_replace(
    '/(https?:\/\/[\w\-\.\/\?=&%#]+[^\s"<]*)/i',
    '<a href="$1" target="_blank" style="color: #0d6efd">$1</a>',
    $text
  );
}

// ============================
// LEITURA DO error_log
// ============================
$logPath = __DIR__ . '/error_log';
$logs = file_exists($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

$debugPath = __DIR__ . '/log_debug.txt';
$logDebug = file_exists($debugPath) ? file($debugPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Perguntas Pendentes</title>
  <style>
    :root {
      --bg-light: #f4f4f4;
      --bg-dark: #1e1e1e;
      --text-light: #fff;
      --text-dark: #111;
      --card-bg: #ffffff;
      --card-bg-dark: #2a2a2a;
      --primary: #0d6efd;
      --success: #198754;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 20px;
      background: var(--bg-light);
      color: var(--text-dark);
      transition: background 0.3s, color 0.3s;
    }
    body.dark {
      background: var(--bg-dark);
      color: var(--text-light);
    }
    .top-bar {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px;
      margin-bottom: 20px;
    }
    .top-bar a, .toggle-btn {
      background: var(--primary);
      color: white;
      padding: 10px 16px;
      border-radius: 6px;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }
    h1 {
      text-align: center;
    }
    #filtro, #limite {
      margin: 10px auto;
      display: block;
      padding: 10px;
      width: 280px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }
    .card {
      background: var(--card-bg);
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      transition: background 0.3s;
    }
    body.dark .card {
      background: var(--card-bg-dark);
    }
    textarea, input[type="text"] {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      margin-bottom: 15px;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
    .btn {
      background: var(--success);
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 6px;
      cursor: pointer;
    }
    .timestamp {
      text-align: right;
      font-size: 0.85rem;
      opacity: 0.7;
    }
    .origem {
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 5px;
    }
  </style>
</head>
<body>

<!-- Painel Resumo Superior -->
<div style="display:flex;justify-content:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:10px;">
  <div style="background:#0d6efd;color:#fff;padding:12px 20px;border-radius:8px;">
    🟡 Pendentes: <strong><?= $total_pendentes ?></strong>
  </div>
  <div style="background:#198754;color:#fff;padding:12px 20px;border-radius:8px;">
    🤖 Por IA (DeepSeek): <strong><?= $total_ia ?></strong>
  </div>
  <div style="background:#343a40;color:#fff;padding:12px 20px;border-radius:8px;">
    📚 Total de Respostas: <strong><?= $total_respostas ?></strong>
  </div>

</div>

<h1>📋 Admin - Perguntas Pendentes</h1>

<div class="top-bar">
<a href="https://platform.deepseek.com/api_keys" target="_blank">🔑 api_keys</a>
  <a href="index.html" target="_blank">🔗 Chat</a>
  <a href="estatisticas.php" target="_blank">📊 Estatísticas</a>
  <a href="scraper.php" target="_blank">🧹 Scraper</a>
  <button class="toggle-btn" onclick="toggleTheme()">🌓 Modo</button>
</div>

<input type="text" id="filtro" placeholder="Filtrar por palavra...">
<input type="number" id="limite" min="1" value="<?= $limite ?>" placeholder="Quantidade a exibir" onchange="mudarLimite(this.value)">

<!-- Container de Cards e Logs lado a lado -->
<div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">

  <div style="flex:1;min-width:300px;">
    <?php if (empty($pendentes)): ?>
      <p style="text-align:center;font-style:italic;">Nenhuma pergunta pendente no momento.</p>
    <?php else: ?>
      <?php foreach ($pendentes as $p): ?>
        <form method="post" class="card">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input type="hidden" name="pergunta" value="<?= htmlspecialchars($p['pergunta']) ?>">

          <p class="origem">👤 Origem: <?= htmlspecialchars($p['origem']) ?></p>
          <p><strong>Pergunta:</strong><br><?= autoLink(htmlspecialchars($p['pergunta'])) ?></p>

          <label>Resposta:</label>
          <textarea name="resposta" rows="4" required></textarea>

          <label>Tema:</label>
          <input type="text" name="tema" placeholder="Ex: XML, SEFAZ, Config">

          <label>Fonte:</label>
          <input type="text" name="fonte" placeholder="Link, sistema, manual...">

          <button type="submit" name="responder" class="btn">Salvar Resposta</button>

          <div class="timestamp">📅 <?= date('d/m/Y H:i', strtotime($p['registrado_em'])) ?></div>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="flex:0 0 40%;min-width:320px;background:#1e1e1e;color:#f1f1f1;padding:20px;border-radius:8px;font-family:monospace;max-height:80vh;overflow:auto;">
    <h2 style="margin-bottom:10px;border-bottom:1px solid #555;">📄 Últimos Logs de Erro</h2>
    <div style="max-height:300px;overflow-y:auto;font-size:0.9rem;">
      <?php if (empty($logs)): ?>
        <p style="color:#aaa;font-style:italic;text-align:center;">Nenhum erro encontrado no momento.</p>
      <?php else: ?>
        <ul style="list-style:none;padding-left:0;">
          <?php foreach (array_slice(array_reverse($logs), 0, 20) as $line): ?>
            <li style="margin-bottom:5px;border-bottom:1px dashed #444;padding:4px 0;">⚠️ <?= htmlspecialchars($line) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <h3 style="margin-top:20px;border-top:1px solid #555;padding-top:10px;">🧠 Log da IA (log_debug.txt)</h3>
    <div style="max-height:200px;overflow-y:auto;font-size:0.85rem;background:#2d2d2d;padding:10px;border-radius:6px;border:1px solid #444;">
      <?php if (empty($logDebug)): ?>
        <p style="color:#aaa;font-style:italic;text-align:center;">Nenhum log recente da IA.</p>
      <?php else: ?>
        <pre style="white-space:pre-wrap;"><?= htmlspecialchars(implode("\n", array_slice(array_reverse($logDebug), 0, 20))) ?></pre>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
document.getElementById('filtro').addEventListener('input', function () {
  const termo = this.value.toLowerCase();
  document.querySelectorAll('.card').forEach(card => {
    card.style.display = card.innerText.toLowerCase().includes(termo) ? 'block' : 'none';
  });
});
function mudarLimite(valor) {
  window.location.href = '?limite=' + valor;
}
function toggleTheme() {
  document.body.classList.toggle('dark');
  localStorage.setItem('modo', document.body.classList.contains('dark') ? 'dark' : 'light');
}
if (localStorage.getItem('modo') === 'dark') {
  document.body.classList.add('dark');
}
</script>

</body>
</html>
