<?php
// ============================================================
// setup_sistema.php - Bootstrap de versão (v3.1 Atualizado)
// ------------------------------------------------------------
// Compatível com arquitetura automática baseada em pastas:
// /login/ , /painel/ , /backend/  (e qualquer outra nova)
// ============================================================

// Detecta a versão pelo nome da pasta (ex: v0.000)
$versaoDetectada = basename(__DIR__);
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v0.000';
}

define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// Caminho absoluto no servidor
$rootDir = rtrim($_SERVER['DOCUMENT_ROOT'] . BASE_PATH, '/') . '/';

// ============================================================
// 🔍 DETECTAR AUTOMATICAMENTE TODAS AS PASTAS DA VERSÃO
// ============================================================
$pastas = [];
$dirs = glob($rootDir . '*', GLOB_ONLYDIR);

if ($dirs !== false) {
    foreach ($dirs as $dir) {
        $pastas[] = basename($dir);
    }
}

// Registrar a existência de cada pasta
$verificacoes = [];
foreach ($pastas as $pasta) {
    $verificacoes[$pasta] = is_dir($rootDir . $pasta);
}

// ============================================================
// 🔧 SAÍDA JAVASCRIPT PARA O FRONTEND
// ============================================================
header('Content-Type: application/javascript; charset=utf-8');

// Log de versão
echo "console.log('%c[Setup] Versão detectada: " . VERSAO_ATUAL . "', 'color:#00ff88;font-weight:bold;');\n";

// Log das pastas detectadas
echo "console.groupCollapsed('%c[Setup] Estrutura detectada', 'color:#007ced;font-weight:bold;');\n";
foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✔️ OK' : '❌ FALTANDO';
    $color  = $ok ? '#00ff88' : '#ff4444';
    echo "console.log('%c" . BASE_PATH . $dir . "/ → " . $status . "', 'color:" . $color . ";');\n";
}
echo "console.groupEnd();\n";

// Variáveis globais
echo "window.SISTEMA_VERSAO = '" . VERSAO_ATUAL . "';\n";
echo "window.SISTEMA_PATH   = '" . BASE_PATH . "';\n";

// Montar objeto SISTEMA_CONFIG
echo "window.SISTEMA_CONFIG = {
  versao: '" . VERSAO_ATUAL . "',
  caminhoBase: '" . BASE_PATH . "',
  caminhos: {\n";

// Criar caminhos automaticamente
foreach ($pastas as $pasta) {
    echo "    " . $pasta . ": '" . BASE_PATH . $pasta . "/',\n";
}

echo "  }\n";
echo "};\n\n";

// ============================================================
// 🔌 Lista de módulos JS do painel (caminhos automáticos)
// ============================================================

// Caminho para pasta JS do painel
$painelJsDir = $rootDir . "painel/js/";
$modulosJs = [];

if (is_dir($painelJsDir)) {
    foreach (glob($painelJsDir . "*.js") as $jsfile) {
        $modulosJs[] = BASE_PATH . "painel/js/" . basename($jsfile);
    }
}

// Exportar módulos
echo "window.SISTEMA_MODULOS = [\n";
foreach ($modulosJs as $mod) {
    echo "  '" . $mod . "',\n";
}
echo "];\n\n";

// Final
echo "console.log('%c[Setup] Ambiente inicializado com sucesso.', 'color:#00ff88;font-weight:bold;');\n";
?>
