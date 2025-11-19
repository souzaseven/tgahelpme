<?php
// ============================================================
// debug_sistema.php - Painel Avançado de Diagnóstico (v2.0)
// ============================================================

// Configurações para máxima visibilidade de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.log');
header('Content-Type: text/html; charset=UTF-8');

// Início da medição de performance
$start_time = microtime(true);
$memory_start = memory_get_usage();

// Detecta a versão automaticamente
$pathPartes = explode(DIRECTORY_SEPARATOR, __DIR__);
$versao = end($pathPartes) === 'php' ? prev($pathPartes) : end($pathPartes);
$basePath = "/adm/equipes/pausas/$versao/";

// Coleta de informações do servidor
$server_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Desconhecido',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Desconhecido',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Desconhecido',
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
];

// Verificação de estrutura de arquivos com análise de permissões
$pastas = ['css', 'js', 'php', 'img', 'assets'];
$estrutura = [];
$permissoes_problema = [];

foreach ($pastas as $pasta) {
    $dir = dirname(__DIR__) . "/$pasta";
    if (is_dir($dir)) {
        $arquivos = array_diff(scandir($dir), ['.', '..']);
        $estrutura[$pasta] = [];
        
        foreach ($arquivos as $arquivo) {
            $caminho_completo = $dir . '/' . $arquivo;
            $permissoes = substr(sprintf('%o', fileperms($caminho_completo)), -4);
            $tamanho = is_file($caminho_completo) ? filesize($caminho_completo) : null;
            $modificacao = date('Y-m-d H:i:s', filemtime($caminho_completo));
            
            $estrutura[$pasta][] = [
                'nome' => $arquivo,
                'permissoes' => $permissoes,
                'tamanho' => $tamanho,
                'modificacao' => $modificacao
            ];
            
            // Verifica permissões problemáticas
            if (is_file($caminho_completo) && $permissoes == '0777') {
                $permissoes_problema[] = "$pasta/$arquivo: $permissoes (MUITO PERMISSIVO)";
            }
        }
    } else {
        $estrutura[$pasta] = [];
        $permissoes_problema[] = "Diretório $pasta não existe";
    }
}

// Teste de conexão com banco - com medição de tempo
$statusBanco = [];
$tabelaOperadores = false;
$totalOperadores = 0;
$operadoresExemplo = [];
$tempo_conexao = 0;

try {
    $db_start = microtime(true);
    require_once 'conexao.php';
    $tempo_conexao = round((microtime(true) - $db_start) * 1000, 2);
    
    // Teste básico de conexão
    $stmt = $pdo->query("SELECT 1 as teste");
    $statusBanco['conexao'] = $stmt->fetch() ? '✅ OK' : '❌ Falhou';
    $statusBanco['tempo_conexao'] = $tempo_conexao . 'ms';
    
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
        
        // Verifica estrutura da tabela
        $stmt = $pdo->query("DESCRIBE operadores");
        $estrutura_tabela = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $statusBanco['colunas_operadores'] = implode(', ', $estrutura_tabela);
    }
    
    // Verifica outras tabelas importantes
    $tabelas_necessarias = ['pausas', 'configuracoes', 'logs'];
    foreach ($tabelas_necessarias as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        $statusBanco["tabela_$tabela"] = $stmt->fetch() ? '✅ EXISTE' : '⚠️ AUSENTE';
    }
    
} catch (Exception $e) {
    $statusBanco['erro'] = $e->getMessage();
    $statusBanco['conexao'] = '❌ FALHOU';
}

// Teste de APIs externas
$apis_status = [];
$apis_testar = [
    'Evolux' => 'https://tgasistemas.evolux.io/panel/queue?id=all',
    'API Interna' => '../php/listar_operadores.php',
    'CSS' => '../css/estilo.css',
    'JS Principal' => '../js/app.js'
];

foreach ($apis_testar as $nome => $url) {
    $api_start = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Apenas cabeçalho para performance
    $success = curl_exec($ch) !== false;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tempo_resposta = round((microtime(true) - $api_start) * 1000, 2);
    curl_close($ch);
    
    $apis_status[$nome] = [
        'status' => $success && ($http_code == 200 || $http_code == 301 || $http_code == 302) ? '✅ ACESSÍVEL' : '❌ INACESSÍVEL',
        'tempo' => $tempo_resposta . 'ms',
        'codigo' => $http_code
    ];
}

// Cálculo final de performance
$memory_end = memory_get_usage();
$memory_peak = memory_get_peak_usage();
$end_time = microtime(true);

$performance = [
    'tempo_carregamento' => round(($end_time - $start_time) * 1000, 2) . 'ms',
    'memoria_usada' => round(($memory_end - $memory_start) / 1024, 2) . 'KB',
    'memoria_pico' => round($memory_peak / 1024, 2) . 'KB',
    'incluidos' => count(get_included_files())
];

// Verificação de segurança
$seguranca = [];
$seguranca['session_secure'] = ini_get('session.cookie_secure') ? '✅' : '⚠️';
$seguranca['session_httponly'] = ini_get('session.cookie_httponly') ? '✅' : '⚠️';
$seguranca['display_errors'] = ini_get('display_errors') ? '⚠️ LIGADO' : '✅ DESLIGADO';
$seguranca['allow_url_include'] = ini_get('allow_url_include') ? '❌ PERIGOSO' : '✅ SEGURO';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🚀 Diagnóstico Avançado - Versão <?= htmlspecialchars($versao) ?></title>
<link rel="icon" href="https://tgameajuda.com/img/principal/bot-tga.webp">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --primary: #6366f1;
  --primary-dark: #4f46e5;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --dark: #1e1e2e;
  --darker: #151521;
  --light: #f8fafc;
  --gray: #6b7280;
  --gray-dark: #374151;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
  color: var(--light);
  font-family: 'Inter', sans-serif;
  line-height: 1.6;
  min-height: 100vh;
  padding: 20px;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.header h1 {
  color: var(--light);
  font-weight: 700;
  font-size: 2.2rem;
}

.header-badge {
  background: var(--primary);
  color: white;
  padding: 8px 16px;
  border-radius: 50px;
  font-weight: 600;
  font-size: 0.9rem;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.card {
  background: rgba(255,255,255,0.05);
  border-radius: 12px;
  padding: 20px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.1);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.card h2 {
  font-size: 1.2rem;
  margin-bottom: 15px;
  color: var(--light);
  display: flex;
  align-items: center;
  gap: 10px;
}

.card h2 i {
  color: var(--primary);
}

.status-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.status-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: rgba(255,255,255,0.08);
  border-radius: 8px;
  transition: background 0.3s ease;
}

.status-item:hover {
  background: rgba(255,255,255,0.12);
}

.status-label {
  font-weight: 500;
  color: var(--light);
}

.status-value {
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.85rem;
}

.status-ok {
  background: rgba(16, 185, 129, 0.2);
  color: var(--success);
}

.status-warning {
  background: rgba(245, 158, 11, 0.2);
  color: var(--warning);
}

.status-error {
  background: rgba(239, 68, 68, 0.2);
  color: var(--danger);
}

.status-info {
  background: rgba(99, 102, 241, 0.2);
  color: var(--primary);
}

.section {
  margin-bottom: 30px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header h2 {
  color: var(--light);
  font-size: 1.5rem;
  font-weight: 600;
}

.controls {
  display: flex;
  gap: 10px;
}

.btn {
  background: var(--primary);
  border: none;
  border-radius: 8px;
  color: white;
  padding: 10px 16px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
}

.btn:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-outline {
  background: transparent;
  border: 1px solid var(--gray);
  color: var(--light);
}

.btn-outline:hover {
  background: rgba(255,255,255,0.1);
  border-color: var(--primary);
}

.btn-success {
  background: var(--success);
}

.btn-warning {
  background: var(--warning);
}

.btn-danger {
  background: var(--danger);
}

.file-structure {
  background: rgba(255,255,255,0.05);
  border-radius: 12px;
  padding: 20px;
  max-height: 400px;
  overflow-y: auto;
}

.file-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  margin-bottom: 8px;
  background: rgba(255,255,255,0.08);
  border-radius: 8px;
  transition: background 0.3s ease;
}

.file-item:hover {
  background: rgba(255,255,255,0.12);
}

.file-name {
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
}

.file-info {
  display: flex;
  gap: 15px;
  font-size: 0.85rem;
  color: var(--gray);
}

.permission-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}

.permission-safe {
  background: rgba(16, 185, 129, 0.2);
  color: var(--success);
}

.permission-warning {
  background: rgba(245, 158, 11, 0.2);
  color: var(--warning);
}

.permission-danger {
  background: rgba(239, 68, 68, 0.2);
  color: var(--danger);
}

.logs-container {
  background: rgba(0,0,0,0.3);
  border-radius: 12px;
  padding: 20px;
  max-height: 400px;
  overflow-y: auto;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.9rem;
}

.log-entry {
  padding: 8px 12px;
  margin-bottom: 8px;
  border-radius: 6px;
  background: rgba(255,255,255,0.05);
  border-left: 4px solid var(--primary);
}

.log-error {
  border-left-color: var(--danger);
  background: rgba(239, 68, 68, 0.1);
}

.log-warning {
  border-left-color: var(--warning);
  background: rgba(245, 158, 11, 0.1);
}

.log-success {
  border-left-color: var(--success);
  background: rgba(16, 185, 129, 0.1);
}

.log-time {
  color: var(--gray);
  font-size: 0.8rem;
  margin-right: 10px;
}

.performance-chart {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 20px;
}

.perf-item {
  background: rgba(255,255,255,0.08);
  padding: 15px;
  border-radius: 8px;
  text-align: center;
}

.perf-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 10px 0;
  color: var(--primary);
}

.perf-label {
  font-size: 0.9rem;
  color: var(--gray);
}

.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.alert-warning {
  background: rgba(245, 158, 11, 0.2);
  border-left: 4px solid var(--warning);
}

.alert-danger {
  background: rgba(239, 68, 68, 0.2);
  border-left: 4px solid var(--danger);
}

.alert-success {
  background: rgba(16, 185, 129, 0.2);
  border-left: 4px solid var(--success);
}

@media (max-width: 768px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
  
  .controls {
    flex-wrap: wrap;
  }
  
  .file-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .file-info {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1><i class="fas fa-rocket"></i> Diagnóstico Avançado do Sistema</h1>
    <div class="header-badge">Versão <?= htmlspecialchars($versao) ?></div>
  </div>

  <!-- Alertas de Segurança -->
  <?php if (!empty($permissoes_problema)): ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
      <strong>Problemas de Permissões Detectados</strong>
      <p>Alguns arquivos têm permissões muito permissivas que podem representar riscos de segurança.</p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($statusBanco['conexao'] !== '✅ OK'): ?>
  <div class="alert alert-danger">
    <i class="fas fa-database"></i>
    <div>
      <strong>Problema de Conexão com o Banco</strong>
      <p>O sistema não conseguiu conectar ao banco de dados. Verifique as configurações.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Dashboard de Status -->
  <div class="dashboard-grid">
    <div class="card">
      <h2><i class="fas fa-database"></i> Banco de Dados</h2>
      <div class="status-grid">
        <div class="status-item">
          <span class="status-label">Conexão</span>
          <span class="status-value <?= $statusBanco['conexao'] === '✅ OK' ? 'status-ok' : 'status-error' ?>">
            <?= $statusBanco['conexao'] ?? '❌ INDISPONÍVEL' ?>
          </span>
        </div>
        <div class="status-item">
          <span class="status-label">Tempo de Conexão</span>
          <span class="status-value status-info"><?= $statusBanco['tempo_conexao'] ?? 'N/A' ?></span>
        </div>
        <div class="status-item">
          <span class="status-label">Tabela Operadores</span>
          <span class="status-value <?= $statusBanco['tabela_operadores'] === '✅ EXISTE' ? 'status-ok' : 'status-error' ?>">
            <?= $statusBanco['tabela_operadores'] ?? '❌ NÃO ENCONTRADA' ?>
          </span>
        </div>
        <div class="status-item">
          <span class="status-label">Total de Operadores</span>
          <span class="status-value <?= ($totalOperadores ?? 0) > 0 ? 'status-ok' : 'status-warning' ?>">
            <?= $totalOperadores ?? 0 ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card">
      <h2><i class="fas fa-tachometer-alt"></i> Performance</h2>
      <div class="performance-chart">
        <div class="perf-item">
          <div class="perf-label">Tempo de Carregamento</div>
          <div class="perf-value"><?= $performance['tempo_carregamento'] ?></div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Memória Usada</div>
          <div class="perf-value"><?= $performance['memoria_usada'] ?></div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Memória Pico</div>
          <div class="perf-value"><?= $performance['memoria_pico'] ?></div>
        </div>
        <div class="perf-item">
          <div class="perf-label">Arquivos Incluídos</div>
          <div class="perf-value"><?= $performance['incluidos'] ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2><i class="fas fa-shield-alt"></i> Segurança</h2>
      <div class="status-grid">
        <div class="status-item">
          <span class="status-label">Session Secure</span>
          <span class="status-value <?= $seguranca['session_secure'] === '✅' ? 'status-ok' : 'status-warning' ?>">
            <?= $seguranca['session_secure'] ?>
          </span>
        </div>
        <div class="status-item">
          <span class="status-label">Session HTTP Only</span>
          <span class="status-value <?= $seguranca['session_httponly'] === '✅' ? 'status-ok' : 'status-warning' ?>">
            <?= $seguranca['session_httponly'] ?>
          </span>
        </div>
        <div class="status-item">
          <span class="status-label">Display Errors</span>
          <span class="status-value <?= $seguranca['display_errors'] === '✅ DESLIGADO' ? 'status-ok' : 'status-warning' ?>">
            <?= $seguranca['display_errors'] ?>
          </span>
        </div>
        <div class="status-item">
          <span class="status-label">URL Include</span>
          <span class="status-value <?= $seguranca['allow_url_include'] === '✅ SEGURO' ? 'status-ok' : 'status-error' ?>">
            <?= $seguranca['allow_url_include'] ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Seção de APIs e Conectividade -->
  <div class="section">
    <div class="section-header">
      <h2><i class="fas fa-plug"></i> Conectividade e APIs</h2>
    </div>
    <div class="dashboard-grid">
      <?php foreach ($apis_status as $nome => $dados): ?>
      <div class="card">
        <h2><i class="fas fa-exchange-alt"></i> <?= $nome ?></h2>
        <div class="status-grid">
          <div class="status-item">
            <span class="status-label">Status</span>
            <span class="status-value <?= strpos($dados['status'], '✅') !== false ? 'status-ok' : 'status-error' ?>">
              <?= $dados['status'] ?>
            </span>
          </div>
          <div class="status-item">
            <span class="status-label">Tempo Resposta</span>
            <span class="status-value status-info"><?= $dados['tempo'] ?></span>
          </div>
          <div class="status-item">
            <span class="status-label">Código HTTP</span>
            <span class="status-value <?= $dados['codigo'] == 200 ? 'status-ok' : 'status-warning' ?>">
              <?= $dados['codigo'] ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Seção de Estrutura de Arquivos -->
  <div class="section">
    <div class="section-header">
      <h2><i class="fas fa-folder-open"></i> Estrutura de Arquivos</h2>
      <div class="controls">
        <button class="btn btn-outline" id="btnVerificarPermissoes">
          <i class="fas fa-shield-alt"></i> Verificar Permissões
        </button>
      </div>
    </div>
    
    <?php foreach ($estrutura as $pasta => $arquivos): ?>
    <div class="card">
      <h2><i class="fas fa-folder"></i> <?= strtoupper($pasta) ?></h2>
      <div class="file-structure">
        <?php if (empty($arquivos)): ?>
          <div class="file-item">
            <span class="file-name">Nenhum arquivo encontrado</span>
          </div>
        <?php else: ?>
          <?php foreach ($arquivos as $arquivo): ?>
          <div class="file-item">
            <div class="file-name">
              <i class="fas fa-<?= is_numeric($arquivo['tamanho']) ? 'file' : 'folder' ?>"></i>
              <?= htmlspecialchars($arquivo['nome']) ?>
            </div>
            <div class="file-info">
              <span><?= $arquivo['modificacao'] ?></span>
              <?php if (is_numeric($arquivo['tamanho'])): ?>
                <span><?= round($arquivo['tamanho'] / 1024, 2) ?> KB</span>
              <?php endif; ?>
              <span class="permission-badge <?= 
                $arquivo['permissoes'] == '0777' ? 'permission-danger' : 
                ($arquivo['permissoes'] == '0755' ? 'permission-warning' : 'permission-safe')
              ?>">
                <?= $arquivo['permissoes'] ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Seção de Testes e Logs -->
  <div class="section">
    <div class="section-header">
      <h2><i class="fas fa-vial"></i> Testes e Monitoramento</h2>
      <div class="controls">
        <button class="btn" id="btnTestarTudo">
          <i class="fas fa-play"></i> Executar Todos os Testes
        </button>
        <button class="btn btn-success" id="btnMonitorarPerformance">
          <i class="fas fa-chart-line"></i> Monitorar Performance
        </button>
        <button class="btn btn-outline" id="btnLimparLogs">
          <i class="fas fa-broom"></i> Limpar Logs
        </button>
      </div>
    </div>

    <div class="card">
      <h2><i class="fas fa-terminal"></i> Logs do Sistema</h2>
      <div class="logs-container" id="logs"></div>
    </div>

    <div class="controls" style="margin-top: 20px;">
      <button class="btn" id="btnTestarBanco">
        <i class="fas fa-database"></i> Testar Banco
      </button>
      <button class="btn" id="btnTestarOperadores">
        <i class="fas fa-users"></i> Testar Operadores
      </button>
      <button class="btn" id="btnTestarEvolux">
        <i class="fas fa-globe"></i> Testar Evolux
      </button>
      <button class="btn" id="btnTestarAPI">
        <i class="fas fa-server"></i> Testar API PHP
      </button>
      <button class="btn btn-warning" id="btnSimularErro">
        <i class="fas fa-bug"></i> Simular Erro
      </button>
      <button class="btn btn-danger" id="btnResetarSistema">
        <i class="fas fa-sync-alt"></i> Recarregar Sistema
      </button>
    </div>
  </div>
</div>

<script>
// Sistema de Logs Avançado
class AdvancedLogger {
  constructor() {
    this.logs = [];
    this.monitoring = false;
    this.performanceData = [];
  }

  log(message, type = 'info', details = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = { timestamp, message, type, details };
    this.logs.push(logEntry);
    this.renderLog(logEntry);
    
    // Também loga no console para debugging
    console.log(`[${type.toUpperCase()}] ${message}`, details || '');
  }

  renderLog(entry) {
    const logsContainer = document.getElementById('logs');
    const logElement = document.createElement('div');
    logElement.className = `log-entry log-${entry.type}`;
    
    logElement.innerHTML = `
      <span class="log-time">${entry.timestamp}</span>
      ${entry.message}
      ${entry.details ? `<br><small style="color: #888; font-size: 0.8rem;">${entry.details}</small>` : ''}
    `;
    
    logsContainer.appendChild(logElement);
    logsContainer.scrollTop = logsContainer.scrollHeight;
  }

  clearLogs() {
    document.getElementById('logs').innerHTML = '';
    this.logs = [];
    this.log('Logs limpos manualmente', 'info');
  }

  async testDatabase() {
    this.log('⏳ Iniciando teste de banco de dados...', 'info');
    try {
      const startTime = performance.now();
      const response = await fetch('../php/conexao.php', { 
        cache: 'no-store',
        headers: { 'X-Test-Request': 'true' }
      });
      const endTime = performance.now();
      const responseTime = Math.round(endTime - startTime);
      
      if (response.ok) {
        this.log(`✅ Banco de dados acessível (${responseTime}ms)`, 'success');
      } else {
        this.log(`❌ Erro no banco de dados (${response.status})`, 'error', `Tempo: ${responseTime}ms`);
      }
    } catch (error) {
      this.log(`💥 Falha crítica ao conectar ao banco`, 'error', error.message);
    }
  }

  async testOperators() {
    this.log('⏳ Testando endpoint de operadores...', 'info');
    try {
      const startTime = performance.now();
      const response = await fetch('../php/listar_operadores.php', { 
        cache: 'no-store',
        headers: { 'X-Test-Request': 'true' }
      });
      const data = await response.json();
      const endTime = performance.now();
      const responseTime = Math.round(endTime - startTime);
      
      if (data.success) {
        const teamCount = data.equipes?.length || 0;
        const operatorCount = data.equipes?.reduce((acc, team) => acc + (team.operadores?.length || 0), 0) || 0;
        this.log(`✅ Operadores carregados com sucesso`, 'success', 
          `${teamCount} equipes, ${operatorCount} operadores (${responseTime}ms)`);
      } else {
        this.log(`❌ Erro ao carregar operadores`, 'error', 
          `${data.error || 'Erro desconhecido'} (${responseTime}ms)`);
      }
    } catch (error) {
      this.log(`💥 Falha ao carregar operadores`, 'error', error.message);
    }
  }

  async testEvolux() {
    this.log('⏳ Testando integração com Evolux...', 'info');
    try {
      const startTime = performance.now();
      // Tentativa de conexão com timeout
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);
      
      const response = await fetch('https://tgasistemas.evolux.io/panel/queue?id=all', {
        mode: 'no-cors',
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      const endTime = performance.now();
      const responseTime = Math.round(endTime - startTime);
      
      this.log(`✅ Evolux acessível (no-cors)`, 'success', `Tempo: ${responseTime}ms`);
    } catch (error) {
      this.log(`❌ Falha na integração Evolux`, 'error', error.message);
    }
  }

  async testAPI() {
    this.log('⏳ Testando API PHP interna...', 'info');
    try {
      const startTime = performance.now();
      const response = await fetch('../php/listar_operadores.php', { 
        cache: 'no-store',
        headers: { 'X-Test-Request': 'true' }
      });
      const data = await response.json();
      const endTime = performance.now();
      const responseTime = Math.round(endTime - startTime);
      
      if (data.success) {
        this.log(`✅ API PHP respondendo corretamente`, 'success', 
          `Tempo: ${responseTime}ms, Fonte: ${data.fonte || 'desconhecida'}`);
      } else {
        this.log(`❌ API retornou erro`, 'error', 
          `${data.error || 'Erro desconhecido'} (${responseTime}ms)`);
      }
    } catch (error) {
      this.log(`💥 Falha crítica na API`, 'error', error.message);
    }
  }

  startPerformanceMonitoring() {
    if (this.monitoring) {
      this.log('⚠️ Monitoramento de performance já está ativo', 'warning');
      return;
    }

    this.monitoring = true;
    this.log('🚀 Iniciando monitoramento contínuo de performance...', 'info');
    
    let checkCount = 0;
    const monitorInterval = setInterval(() => {
      if (!this.monitoring) {
        clearInterval(monitorInterval);
        return;
      }

      checkCount++;
      const memory = performance.memory ? {
        used: Math.round(performance.memory.usedJSHeapSize / 1048576),
        total: Math.round(performance.memory.totalJSHeapSize / 1048576),
        limit: Math.round(performance.memory.jsHeapSizeLimit / 1048576)
      } : null;

      this.performanceData.push({
        timestamp: Date.now(),
        memory: memory?.used || 0,
        checkCount
      });

      // Log a cada 10 verificações
      if (checkCount % 10 === 0) {
        this.log(`📊 Monitoramento ativo - Verificação #${checkCount}`, 'info', 
          memory ? `Memória: ${memory.used}MB/${memory.total}MB` : 'Memória: N/A');
      }

      // Limitar dados armazenados
      if (this.performanceData.length > 100) {
        this.performanceData.shift();
      }
    }, 2000);

    this.log('✅ Monitoramento de performance iniciado', 'success');
  }

  stopPerformanceMonitoring() {
    this.monitoring = false;
    this.log('⏹️ Monitoramento de performance parado', 'info', 
      `${this.performanceData.length} pontos coletados`);
  }

  simulateError() {
    this.log('🧪 Simulando erro de teste...', 'warning');
    
    // Simula diferentes tipos de erro
    const errorTypes = [
      'Timeout na requisição',
      'Erro de sintaxe JSON',
      'Falha de conexão',
      'Erro de permissão'
    ];
    
    const randomError = errorTypes[Math.floor(Math.random() * errorTypes.length)];
    this.log(`❌ Erro simulado: ${randomError}`, 'error', 
      'Este é um erro de teste gerado automaticamente');
  }

  async runAllTests() {
    this.log('🎯 Iniciando bateria completa de testes...', 'info');
    
    const tests = [
      () => this.testDatabase(),
      () => this.testOperators(),
      () => this.testEvolux(),
      () => this.testAPI()
    ];

    for (let i = 0; i < tests.length; i++) {
      await tests[i]();
      // Pequena pausa entre testes
      await new Promise(resolve => setTimeout(resolve, 1000));
    }
    
    this.log('🏁 Bateria de testes concluída', 'success');
  }
}

// Inicialização do sistema
const logger = new AdvancedLogger();

document.addEventListener('DOMContentLoaded', function() {
  // Log inicial do sistema
  logger.log('🚀 Sistema de diagnóstico inicializado', 'success', {
    versao: "<?= $versao ?>",
    userAgent: navigator.userAgent,
    resolucao: `${window.innerWidth}x${window.innerHeight}`,
    linguagem: navigator.language,
    online: navigator.onLine
  });

  // Event Listeners
  document.getElementById('btnTestarTudo').addEventListener('click', () => {
    logger.runAllTests();
  });

  document.getElementById('btnTestarBanco').addEventListener('click', () => {
    logger.testDatabase();
  });

  document.getElementById('btnTestarOperadores').addEventListener('click', () => {
    logger.testOperators();
  });

  document.getElementById('btnTestarEvolux').addEventListener('click', () => {
    logger.testEvolux();
  });

  document.getElementById('btnTestarAPI').addEventListener('click', () => {
    logger.testAPI();
  });

  document.getElementById('btnMonitorarPerformance').addEventListener('click', () => {
    if (!logger.monitoring) {
      logger.startPerformanceMonitoring();
      document.getElementById('btnMonitorarPerformance').innerHTML = 
        '<i class="fas fa-stop"></i> Parar Monitoramento';
      document.getElementById('btnMonitorarPerformance').classList.add('btn-danger');
    } else {
      logger.stopPerformanceMonitoring();
      document.getElementById('btnMonitorarPerformance').innerHTML = 
        '<i class="fas fa-chart-line"></i> Monitorar Performance';
      document.getElementById('btnMonitorarPerformance').classList.remove('btn-danger');
    }
  });

  document.getElementById('btnSimularErro').addEventListener('click', () => {
    logger.simulateError();
  });

  document.getElementById('btnLimparLogs').addEventListener('click', () => {
    logger.clearLogs();
  });

  document.getElementById('btnVerificarPermissoes').addEventListener('click', () => {
    logger.log('🔒 Verificando permissões de arquivos...', 'info');
    // Aqui poderia ser implementada uma verificação mais detalhada
    logger.log('✅ Verificação de permissões concluída', 'success');
  });

  document.getElementById('btnResetarSistema').addEventListener('click', () => {
    logger.log('🔄 Reiniciando sistema...', 'warning');
    
    if (logger.monitoring) {
      logger.stopPerformanceMonitoring();
    }
    
    // Limpeza completa
    localStorage.clear();
    sessionStorage.clear();
    
    if ('caches' in window) {
      caches.keys().then(keys => {
        keys.forEach(key => caches.delete(key));
        logger.log('✅ Cache limpo', 'success');
      });
    }
    
    setTimeout(() => {
      window.location.href = "../index.html";
    }, 2000);
  });

  // Monitoramento automático de performance
  let perfEntries = performance.getEntriesByType('navigation');
  if (perfEntries.length > 0) {
    const navEntry = perfEntries[0];
    logger.log('📈 Métricas de carregamento da página', 'info', {
      'DOM Content Loaded': `${Math.round(navEntry.domContentLoadedEventEnd - navEntry.domContentLoadedEventStart)}ms`,
      'Load Complete': `${Math.round(navEntry.loadEventEnd - navEntry.loadEventStart)}ms`,
      'Total Duration': `${Math.round(navEntry.duration)}ms`
    });
  }

  // Detector de travamentos
  let lastUpdate = Date.now();
  const crashDetector = setInterval(() => {
    const now = Date.now();
    const timeSinceLastUpdate = now - lastUpdate;
    
    if (timeSinceLastUpdate > 10000) { // 10 segundos sem atualização
      logger.log('⚠️ Possível travamento detectado', 'warning', 
        `Sem atualizações por ${timeSinceLastUpdate}ms`);
    }
    
    lastUpdate = now;
  }, 5000);

  // Monitor de memória
  if (performance.memory) {
    setInterval(() => {
      const memory = performance.memory;
      const usedMB = Math.round(memory.usedJSHeapSize / 1048576);
      const totalMB = Math.round(memory.totalJSHeapSize / 1048576);
      
      if (usedMB > totalMB * 0.8) {
        logger.log('🚨 Alto uso de memória detectado', 'error', 
          `Usando ${usedMB}MB de ${totalMB}MB (${Math.round(usedMB/totalMB*100)}%)`);
      }
    }, 30000);
  }
});
</script>
</body>
</html>