<?php
// ============================================================
// setup_sistema.php - Autodetecção e bootstrap de versão
// ============================================================

// Detecta a pasta da versão (ex: v1.7)
$versaoDetectada = basename(__DIR__);
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v1.0';
}

define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// Caminhos físicos para checagem
$rootDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH;
$pastas = ['css', 'js', 'php'];
$verificacoes = [];
foreach ($pastas as $pasta) {
    $verificacoes[$pasta] = is_dir($rootDir . $pasta);
}

// ============================================================
// 🔧 Saída JavaScript
// ============================================================
header('Content-Type: application/javascript; charset=utf-8');
echo "console.log('%c[Setup] Versão detectada: " . VERSAO_ATUAL . "', 'color:#00ff88;font-weight:bold;');\n";
echo "console.groupCollapsed('%c[Setup] Verificação de pastas', 'color:#007ced;font-weight:bold;');\n";
foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✅ OK' : '❌ Faltando';
    $color  = $ok ? '#00ff88' : '#ff4444';
    echo "console.log('%c" . BASE_PATH . $dir . "/ → " . $status . "', 'color:" . $color . ";');\n";
}
echo "console.groupEnd();\n";

echo "window.SISTEMA_VERSAO = '" . VERSAO_ATUAL . "';\n";
echo "window.SISTEMA_PATH = '" . BASE_PATH . "';\n";
echo "window.SISTEMA_CONFIG = {
  versao: '" . VERSAO_ATUAL . "',
  caminhos: {
    css: '" . BASE_PATH . "css/',
    js: '" . BASE_PATH . "js/',
    php: '" . BASE_PATH . "php/'
  }
};\n\n";

echo "// 🚀 Carregando apenas main.js...\n";
echo "(function(){\n";
echo "  const s = document.createElement('script');\n";
echo "  s.src = window.SISTEMA_CONFIG.caminhos.js + 'main.js?v=' + window.SISTEMA_VERSAO + '&t=' + Date.now();\n";
echo "  s.defer = true;\n";
echo "  s.onload = () => console.log('%c✅ main.js carregado com sucesso.', 'color:#00ff88;font-weight:bold;');\n";
echo "  s.onerror = () => console.error('❌ Falha ao carregar main.js');\n";
echo "  document.head.appendChild(s);\n";
echo "})();\n";
?>
