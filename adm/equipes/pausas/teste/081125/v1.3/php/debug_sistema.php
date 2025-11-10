<?php
// ============================================================
// debug_sistema.php - Painel de diagnóstico do Sistema de Pausas (corrigido)
// Detecta automaticamente a versão mesmo dentro de /php/
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Detecta a versão automaticamente mesmo se estiver dentro de /php/
$pathPartes = explode(DIRECTORY_SEPARATOR, __DIR__);
$versao = end($pathPartes) === 'php' ? prev($pathPartes) : end($pathPartes);
$basePath = "/adm/equipes/pausas/$versao/";

$pastas = ['css', 'js', 'php'];
$estrutura = [];

foreach ($pastas as $pasta) {
    $dir = dirname(__DIR__) . "/$pasta";
    if (is_dir($dir)) {
        $arquivos = array_diff(scandir($dir), ['.', '..']);
        $estrutura[$pasta] = array_values($arquivos);
    } else {
        $estrutura[$pasta] = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>🧩 Diagnóstico do Sistema - Versão <?= htmlspecialchars($versao) ?></title>
<link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
  background: #0d1117;
  color: #e6edf3;
  font-family: Consolas, monospace;
  padding: 20px;
}
h1 {
  color: #00c3ff;
  border-bottom: 2px solid #00c3ff;
  padding-bottom: 8px;
}
pre {
  background: #161b22;
  padding: 15px;
  border-radius: 10px;
  overflow-x: auto;
}
.section { margin-bottom: 30px; }
button {
  background: #007ced; border: none; border-radius: 8px;
  color: #fff; padding: 8px 14px; cursor: pointer;
  margin-right: 10px; transition: 0.2s;
}
button:hover { background: #0090ff; }
#logs {
  background: #161b22;
  padding: 10px;
  border-radius: 8px;
  max-height: 300px;
  overflow-y: auto;
  font-size: 0.9rem;
}
</style>
</head>
<body>
<h1>🧩 Diagnóstico do Sistema - Versão <?= htmlspecialchars($versao) ?></h1>

<div class="section">
  <h2>📁 Estrutura de Arquivos</h2>
  <?php foreach ($estrutura as $pasta => $arquivos): ?>
  <h3>📂 <?= strtoupper($pasta) ?></h3>
  <pre><?= implode("\n", $arquivos) ?: "Nenhum arquivo encontrado" ?></pre>
  <?php endforeach; ?>
</div>

<div class="section">
  <h2>🧠 Informações do Sistema</h2>
  <pre id="infoSistema">Carregando informações...</pre>
</div>

<div class="section">
  <h2>🔍 Testes de Conexão e Logs</h2>
  <button id="btnTestarBanco"><i class="fas fa-database"></i> Testar Banco</button>
  <button id="btnTestarEvolux"><i class="fas fa-globe"></i> Testar Evolux</button>
  <button id="btnTestarAPI"><i class="fas fa-server"></i> Testar PHP</button>
  <button id="btnLimparLogs"><i class="fas fa-broom"></i> Limpar Logs</button>
  <button id="btnResetarSistema"><i class="fas fa-sync-alt"></i> Recarregar Sistema</button>
  <div id="logs"></div>
</div>

<script>
const logDiv = document.getElementById("logs");
function log(msg, ok=true) {
  const cor = ok ? "#00ff88" : "#ff5555";
  const linha = document.createElement("div");
  linha.innerHTML = `<span style="color:${cor}">${new Date().toLocaleTimeString()}</span> → ${msg}`;
  logDiv.appendChild(linha);
  logDiv.scrollTop = logDiv.scrollHeight;
}

// 🧠 Info do sistema
document.getElementById("infoSistema").textContent = JSON.stringify({
  versao: "<?= $versao ?>",
  path: "<?= $basePath ?>",
  navegador: navigator.userAgent,
  resolucao: `${window.innerWidth}x${window.innerHeight}`,
  operador: localStorage.getItem("operador_nome") || "não logado",
  modo_admin: localStorage.getItem("modo_admin") === "true"
}, null, 2);

// 🔍 Testes
document.getElementById("btnTestarBanco").onclick = async () => {
  log("⏳ Testando conexão com banco...");
  try {
    const resp = await fetch("../php/conexao.php", { cache: "no-store" });
    log(resp.ok ? "✅ Banco acessível" : "❌ Erro no banco", resp.ok);
  } catch (e) { log("❌ Falha ao conectar ao banco", false); }
};

document.getElementById("btnTestarEvolux").onclick = async () => {
  log("🌐 Testando integração Evolux...");
  try {
    const resp = await fetch("https://tgasistemas.evolux.io/panel/queue?id=all", { mode: "no-cors" });
    log("✅ Acesso ao painel Evolux OK (no-cors)", true);
  } catch (e) { log("❌ Falha na integração Evolux", false); }
};

document.getElementById("btnTestarAPI").onclick = async () => {
  log("🧩 Testando API listar_operadores.php...");
  try {
    const resp = await fetch("../php/listar_operadores.php", { cache: "no-store" });
    const data = await resp.json();
    if (data.success) log("✅ API respondeu com sucesso (" + data.equipes.length + " equipes)");
    else log("❌ API retornou erro: " + data.error, false);
  } catch (e) { log("❌ Erro ao testar API: " + e.message, false); }
};

document.getElementById("btnLimparLogs").onclick = () => {
  logDiv.innerHTML = "";
  log("🧹 Logs limpos manualmente.");
};

// ♻️ Resetar e recarregar sistema
document.getElementById("btnResetarSistema").onclick = () => {
  log("🧹 Limpando cache, localStorage e sessionStorage...");
  try {
    localStorage.clear();
    sessionStorage.clear();
    caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
    log("✅ Limpeza concluída. Redirecionando para index.html...");
    setTimeout(() => { window.location.href = "../index.html"; }, 1500);
  } catch (e) {
    log("❌ Falha ao limpar cache: " + e.message, false);
  }
};
</script>

</body>
</html>
