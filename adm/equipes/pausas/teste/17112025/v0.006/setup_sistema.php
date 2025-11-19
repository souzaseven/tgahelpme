<?php
// ============================================================
// setup_sistema.php - Bootstrap de versão (v3.0 Oficial)
// Compatível com nova arquitetura:
// /login/ , /painel/ , /backend/
// ============================================================

// Detecta a versão pelo nome da pasta (ex: v0.000)
$versaoDetectada = basename(__DIR__);
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v0.000';
}

define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// Caminhos absolutos
$rootDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH;

// Pastas que devem existir na nova arquitetura
$pastas = [
    'login',
    'painel',
    'backend'
];

$verificacoes = [];
foreach ($pastas as $pasta) {
    $verificacoes[$pasta] = is_dir($rootDir . $pasta);
}

// ============================================================
// 🔧 SAÍDA JAVASCRIPT
// ============================================================
header('Content-Type: application/javascript; charset=utf-8');

// Log versão
echo "console.log('%c[Setup] Versão detectada: " . VERSAO_ATUAL . "', 'color:#00ff88;font-weight:bold;');\n";

// Log pastas
echo "console.groupCollapsed('%c[Setup] Estrutura do Sistema', 'color:#007ced;font-weight:bold;');\n";
foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✔️ OK' : '❌ FALTANDO';
    $color  = $ok ? '#00ff88' : '#ff4444';
    echo "console.log('%c" . BASE_PATH . $dir . "/ → " . $status . "', 'color:" . $color . ";');\n";
}
echo "console.groupEnd();\n";

// Variáveis globais JS
echo "window.SISTEMA_VERSAO = '" . VERSAO_ATUAL . "';\n";
echo "window.SISTEMA_PATH = '" . BASE_PATH . "';\n";

// Nova estrutura oficial de caminhos
echo "window.SISTEMA_CONFIG = {
  versao: '" . VERSAO_ATUAL . "',
  caminhoBase: '" . BASE_PATH . "',
  caminhos: {
    login: '" . BASE_PATH . "login/',
    painel: '" . BASE_PATH . "painel/',
    backend: '" . BASE_PATH . "backend/'
  }
};\n\n";

// Lista de módulos de JS do painel
echo "window.SISTEMA_MODULOS = [
  'painel/js/bootstrap.js',
  'painel/js/operadores.js',
  'painel/js/equipes.js',
  'painel/js/fila.js',
  'painel/js/pausa.js',
  'painel/js/troca.js',
  'painel/js/notificacoes.js',
  'painel/js/preferencias.js'
];\n";

// Final
echo "console.log('%c[Setup] Ambiente inicializado com sucesso.', 'color:#00ff88;font-weight:bold;');\n";
?>
