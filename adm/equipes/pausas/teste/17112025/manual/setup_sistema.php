<?php
// ============================================================
// setup_sistema.php - Autodetecção e bootstrap de versão (v2.0)
// ============================================================

// Detecta a pasta da versão (ex: v1.7)
$versaoDetectada = basename(__DIR__);
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v1.0';
}

define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// Caminhos físicos para verificação
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

// Log resumido
echo "console.log('%c[Setup] Versão detectada: " . VERSAO_ATUAL . "', 'color:#00ff88;font-weight:bold;');\n";

// Log detalhado
echo "console.groupCollapsed('%c[Setup] Verificação de pastas', 'color:#007ced;font-weight:bold;');\n";
foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✔️ OK' : '❌ FALTANDO';
    $color  = $ok ? '#00ff88' : '#ff4444';
    echo "console.log('%c" . BASE_PATH . $dir . "/ → " . $status . "', 'color:" . $color . ";');\n";
}
echo "console.groupEnd();\n";

// Variáveis globais
echo "window.SISTEMA_VERSAO = '" . VERSAO_ATUAL . "';\n";
echo "window.SISTEMA_PATH = '" . BASE_PATH . "';\n";

echo "window.SISTEMA_CONFIG = {
  versao: '" . VERSAO_ATUAL . "',
  caminhoBase: '" . BASE_PATH . "',
  caminhos: {
    css: '" . BASE_PATH . "css/',
    js: '" . BASE_PATH . "js/',
    php: '" . BASE_PATH . "php/'
  }
};\n\n";

// Lista de JS obrigatórios (auditável)
echo "window.SISTEMA_JS_REQUIRED = [
  'controle_pausa.js',
  'interface_botoes.js',
  'status_cards.js',
  'acoes_operador.js',
  'notificacoes_pausa.js',
  'expiracao_pausa.js',
  'inicializacao.js'
];\n";

// Aviso de carregamento bem-sucedido
echo "console.log('%c[Setup] Ambiente inicializado.', 'color:#00ff88;font-weight:bold;');\n";
?>