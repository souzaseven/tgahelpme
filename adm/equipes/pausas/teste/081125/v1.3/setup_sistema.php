<?php
// ============================================================
// setup_sistema.php - Autodetecção de versão (funciona dentro de cada pasta)
// ============================================================

// 🔹 Detecta automaticamente a pasta da versão (ex: v1.6)
$versaoDetectada = basename(__DIR__);

// 🔹 Fallback de segurança (se não encontrar uma versão válida)
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v1.0';
}

// 🔹 Define constantes globais
define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// 🔹 Caminho físico para verificação
$rootDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH;
$pastas = ['css', 'js', 'php'];

// 🔹 Verifica pastas
$verificacoes = [];
foreach ($pastas as $pasta) {
    $verificacoes[$pasta] = is_dir($rootDir . $pasta);
}

// ============================================================
// 🔧 Saída JavaScript Dinâmica
// ============================================================
header('Content-Type: application/javascript; charset=utf-8');

echo "// ============================================================\n";
echo "// Setup automático - Sistema de Pausas (TGA)\n";
echo "// Versão detectada automaticamente: " . VERSAO_ATUAL . "\n";
echo "// ============================================================\n\n";

echo "window.SISTEMA_VERSAO = \"" . VERSAO_ATUAL . "\";\n";
echo "window.SISTEMA_PATH = \"" . BASE_PATH . "\";\n";
echo "window.SISTEMA_CONFIG = {\n";
echo "  versao: \"" . VERSAO_ATUAL . "\",\n";
echo "  caminhos: {\n";
echo "    css: \"" . BASE_PATH . "css/\",\n";
echo "    js: \"" . BASE_PATH . "js/\",\n";
echo "    php: \"" . BASE_PATH . "php/\"\n";
echo "  }\n";
echo "};\n\n";

// ============================================================
// 💬 Logs no Console
// ============================================================
echo "console.log('%c[Setup] Versão do sistema detectada: " . VERSAO_ATUAL . "', 'color:#00ff88;font-weight:bold;');\n";
echo "console.groupCollapsed('%c[Setup] Verificação de pastas', 'color:#007ced;font-weight:bold;');\n";
foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✅ OK' : '❌ Faltando';
    $color  = $ok ? '#00ff88' : '#ff4444';
    echo "console.log('%c" . BASE_PATH . $dir . "/ -> " . $status . "', 'color:" . $color . ";');\n";
}
echo "console.groupEnd();\n\n";

// ============================================================
// 🧩 Diagnóstico rápido no navegador
// ============================================================
echo "window.verificarSetup = () => {\n";
echo "  console.table({\n";
echo "    'Versão Atual': window.SISTEMA_VERSAO,\n";
echo "    'Caminho Base': window.SISTEMA_PATH,\n";
echo "    'CSS': window.SISTEMA_CONFIG.caminhos.css,\n";
echo "    'JS': window.SISTEMA_CONFIG.caminhos.js,\n";
echo "    'PHP': window.SISTEMA_CONFIG.caminhos.php\n";
echo "  });\n";
echo "  console.log('%c🟢 Setup verificado com sucesso!', 'color:#00ff88;font-weight:bold;');\n";
echo "};\n\n";

echo "// ✅ Setup carregado automaticamente (versão: " . VERSAO_ATUAL . ")\n";
?>
