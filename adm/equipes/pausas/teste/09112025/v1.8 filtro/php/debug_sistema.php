<?php
// ============================================================
// debug_sistema.php - Painel de diagnóstico do Sistema de Pausas (versão original)
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

// Teste de conexão com banco
require_once 'conexao.php';

$statusBanco = [];
$tabelaOperadores = false;
$totalOperadores = 0;
$operadoresExemplo = [];

try {
    // Teste básico de conexão
    $stmt = $pdo->query("SELECT 1 as teste");
    $statusBanco['conexao'] = $stmt->fetch() ? '✅ OK' : '❌ Falhou';
    
    // Verifica se tabela operadores existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'operadores'");
    $tabelaOperadores = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    $statusBanco['tabela_operadores'] = $tabelaOperadores ? '✅ EXISTE' : '❌ NÃO ENCONTRADA';
    
    if ($tabelaOperadores) {
        // Conta operadores
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM operadores");
        $totalOperadores = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $statusBanco['total_operadores'] = $totalOperadores;
        
        // Pega alguns exemplos
        $stmt = $pdo->query("SELECT nome, lider, fila FROM operadores LIMIT 5");
        $operadoresExemplo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $statusBanco['erro'] = $e->getMessage();
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
  background: #1e1e2e;
  color: #e6edf3;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  padding: 20px;
  line-height: 1.6;
}
h1 {
  color: #00c3ff;
  border-bottom: 2px solid #00c3ff;
  padding-bottom: 10px;
  margin-bottom: 20px;
}
h2 {
  color: #00ff88;
  margin-top: 25px;
  margin-bottom: 15px;
}
h3 {
  color: #00c6ff;
  margin: 15px 0 10px 0;
}
pre {
  background: #2d2d44;
  padding: 15px;
  border-radius: 8px;
  overflow-x: auto;
  border-left: 4px solid #007ced;
  font-family: 'Consolas', 'Monaco', monospace;
}
.section { 
  margin-bottom: 30px;
  background: rgba(255,255,255,0.05);
  padding: 20px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.1);
}
button {
  background: #007ced;
  border: none;
  border-radius: 6px;
  color: #fff;
  padding: 10px 16px;
  cursor: pointer;
  margin-right: 10px;
  margin-bottom: 10px;
  transition: all 0.3s ease;
  font-family: inherit;
  font-size: 0.9rem;
}
button:hover { 
  background: #0090ff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 124, 237, 0.3);
}
#logs {
  background: #2d2d44;
  padding: 15px;
  border-radius: 8px;
  max-height: 300px;
  overflow-y: auto;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.9rem;
  border: 1px solid rgba(255,255,255,0.1);
}
.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 15px;
  margin-top: 15px;
}
.status-card {
  background: rgba(255,255,255,0.08);
  padding: 15px;
  border-radius: 8px;
  border-left: 4px solid #00ff88;
}
.status-card.erro {
  border-left-color: #ff4444;
}
.status-card.aviso {
  border-left-color: #ffaa00;
}
.status-card h4 {
  margin: 0 0 8px 0;
  color: #fff;
  font-size: 1rem;
}
.status-card p {
  margin: 0;
  color: #ccc;
  font-size: 0.9rem;
}
.operadores-lista {
  background: #2d2d44;
  padding: 15px;
  border-radius: 8px;
  margin-top: 10px;
}
.operador-item {
  padding: 8px 12px;
  margin: 5px 0;
  background: rgba(255,255,255,0.05);
  border-radius: 6px;
  border-left: 3px solid #00c3ff;
}
.operador-item strong {
  color: #fff;
}
.operador-item span {
  color: #ccc;
  font-size: 0.9rem;
}
</style>
</head>
<body>
<h1>🧩 Diagnóstico do Sistema - Versão <?= htmlspecialchars($versao) ?></h1>

<div class="section">
  <h2>🔍 Status do Sistema</h2>
  <div class="status-grid">
    <div class="status-card <?= $statusBanco['conexao'] === '✅ OK' ? '' : 'erro' ?>">
      <h4>📊 Conexão com Banco</h4>
      <p><?= $statusBanco['conexao'] ?? '❌ INDISPONÍVEL' ?></p>
    </div>
    <div class="status-card <?= $statusBanco['tabela_operadores'] === '✅ EXISTE' ? '' : 'erro' ?>">
      <h4>👥 Tabela Operadores</h4>
      <p><?= $statusBanco['tabela_operadores'] ?? '❌ NÃO ENCONTRADA' ?></p>
    </div>
    <div class="status-card <?= ($totalOperadores ?? 0) > 0 ? '' : 'aviso' ?>">
      <h4>📈 Total de Operadores</h4>
      <p><?= $totalOperadores ?? 0 ?> operadores cadastrados</p>
    </div>
  </div>

  <?php if ($tabelaOperadores && $totalOperadores > 0): ?>
  <h3>📋 Exemplo de Operadores (<?= $totalOperadores ?> no total)</h3>
  <div class="operadores-lista">
    <?php foreach ($operadoresExemplo as $operador): ?>
      <div class="operador-item">
        <strong><?= htmlspecialchars($operador['nome']) ?></strong><br>
        <span>Líder: <?= htmlspecialchars($operador['lider']) ?> | Fila: <?= htmlspecialchars($operador['fila']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="section">
  <h2>📁 Estrutura de Arquivos</h2>
  <?php foreach ($estrutura as $pasta => $arquivos): ?>
  <h3>📂 <?= strtoupper($pasta) ?></h3>
  <pre><?= implode("\n", $arquivos) ?: "Nenhum arquivo encontrado" ?></pre>
  <?php endforeach; ?>
</div>

<div class="section">
  <h2>🧠 Informações do Sistema</h2>
  <pre id="infoSistema">Carregando informações do navegador...</pre>
</div>

<div class="section">
  <h2>🔧 Testes Rápidos</h2>
  <button id="btnTestarBanco"><i class="fas fa-database"></i> Testar Banco</button>
  <button id="btnTestarOperadores"><i class="fas fa-users"></i> Testar Operadores</button>
  <button id="btnTestarEvolux"><i class="fas fa-globe"></i> Testar Evolux</button>
  <button id="btnTestarAPI"><i class="fas fa-server"></i> Testar API PHP</button>
  <button id="btnLimparLogs"><i class="fas fa-broom"></i> Limpar Logs</button>
  <button id="btnResetarSistema"><i class="fas fa-sync-alt"></i> Recarregar Sistema</button>
  
  <div id="logs"></div>
</div>

<script>
const logDiv = document.getElementById("logs");
function log(msg, ok = true) {
  const cor = ok ? "#00ff88" : "#ff5555";
  const icone = ok ? "✅" : "❌";
  const linha = document.createElement("div");
  linha.innerHTML = `<span style="color:${cor}">${icone} ${new Date().toLocaleTimeString()}</span> → ${msg}`;
  logDiv.appendChild(linha);
  logDiv.scrollTop = logDiv.scrollHeight;
}

// Informações do sistema
document.getElementById("infoSistema").textContent = JSON.stringify({
  versao: "<?= $versao ?>",
  path: "<?= $basePath ?>",
  navegador: navigator.userAgent,
  resolucao: `${window.innerWidth}x${window.innerHeight}`,
  operador: localStorage.getItem("operador_nome") || "não logado",
  modo_admin: localStorage.getItem("modo_admin") === "true",
  url_atual: window.location.href
}, null, 2);

// Testes
document.getElementById("btnTestarBanco").onclick = async () => {
  log("⏳ Testando conexão com banco...");
  try {
    const resp = await fetch("../php/conexao.php", { cache: "no-store" });
    log(resp.ok ? "Banco acessível" : "Erro no banco", resp.ok);
  } catch (e) { log("Falha ao conectar ao banco: " + e.message, false); }
};

document.getElementById("btnTestarOperadores").onclick = async () => {
  log("⏳ Testando endpoint de operadores...");
  try {
    const resp = await fetch("../php/listar_operadores.php", { cache: "no-store" });
    const data = await resp.json();
    if (data.success) {
      log(`Operadores carregados (${data.equipes?.length || 0} equipes, fonte: ${data.fonte || 'desconhecida'})`);
      console.log("Dados completos:", data);
    } else {
      log(`Erro nos operadores: ${data.error || 'Desconhecido'}`, false);
    }
  } catch (e) { 
    log("Erro ao carregar operadores: " + e.message, false); 
  }
};

document.getElementById("btnTestarEvolux").onclick = async () => {
  log("⏳ Testando integração Evolux...");
  try {
    const resp = await fetch("https://tgasistemas.evolux.io/panel/queue?id=all", { mode: "no-cors" });
    log("Acesso ao painel Evolux OK (no-cors)", true);
  } catch (e) { log("Falha na integração Evolux", false); }
};

document.getElementById("btnTestarAPI").onclick = async () => {
  log("⏳ Testando API PHP...");
  try {
    const resp = await fetch("../php/listar_operadores.php", { cache: "no-store" });
    const data = await resp.json();
    if (data.success) {
      log(`API respondeu com sucesso (${data.equipes?.length || 0} equipes)`);
    } else {
      log(`API retornou erro: ${data.error || 'Desconhecido'}`, false);
    }
  } catch (e) { log("Erro ao testar API: " + e.message, false); }
};

document.getElementById("btnLimparLogs").onclick = () => {
  logDiv.innerHTML = "";
  log("Logs limpos manualmente.");
};

document.getElementById("btnResetarSistema").onclick = () => {
  log("⏳ Limpando cache e recarregando...");
  try {
    localStorage.clear();
    sessionStorage.clear();
    caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
    log("✅ Limpeza concluída. Redirecionando...");
    setTimeout(() => { window.location.href = "../index.html"; }, 1500);
  } catch (e) {
    log("❌ Falha ao limpar cache: " + e.message, false);
  }
};
</script>

</body>
</html>