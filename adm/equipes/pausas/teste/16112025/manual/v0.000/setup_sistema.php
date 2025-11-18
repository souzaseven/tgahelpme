<?php
// =======================================================================
// setup_sistema.php  
// Sistema de Autodetecção e Bootstrap das versões do Controle de Pausas  
// Versão otimizada v3.0 (2025)
// =======================================================================

// -----------------------------------------------------------
// 🔍 DETECÇÃO AUTOMÁTICA DA VERSÃO (ex: v1.7)
// -----------------------------------------------------------
$versaoDetectada = basename(__DIR__);

// Garante que a pasta esteja no padrão vX.X
if (!preg_match('/^v[0-9.]+$/', $versaoDetectada)) {
    $versaoDetectada = 'v1.0';
}

// Define constantes globais
define('VERSAO_ATUAL', $versaoDetectada);
define('BASE_PATH', "/adm/equipes/pausas/" . VERSAO_ATUAL . "/");

// -----------------------------------------------------------
// 📁 VERIFICAÇÃO DAS PASTAS EXISTENTES
// -----------------------------------------------------------
$rootDir = $_SERVER['DOCUMENT_ROOT'] . BASE_PATH;
$pastas = ['css', 'js', 'php'];
$verificacoes = [];

foreach ($pastas as $pasta) {
    $verificacoes[$pasta] = is_dir($rootDir . $pasta);
}

// -----------------------------------------------------------
// 🔧 SAÍDA EM JAVASCRIPT
// -----------------------------------------------------------
header('Content-Type: application/javascript; charset=utf-8');

// =============================================================
// 📌 LOG DE VERSÃO
// =============================================================
echo "console.log('%c[Setup] Versão detectada: " . VERSAO_ATUAL . "', 
      'color:#00ff88;font-weight:bold;');\n";

// =============================================================
// 📁 LOG DE VERIFICAÇÃO DAS PASTAS
// =============================================================
echo "console.groupCollapsed('%c[Setup] Verificação de pastas', 
      'color:#007ced;font-weight:bold;');\n";

foreach ($verificacoes as $dir => $ok) {
    $status = $ok ? '✔️ OK' : '❌ FALTANDO';
    $color  = $ok ? '#00ff88' : '#ff4444';

    echo "console.log('%c" . BASE_PATH . "$dir/ → $status', 'color:$color;');\n";
}

echo "console.groupEnd();\n\n";

// -----------------------------------------------------------
// 🌐 VARIÁVEIS GLOBAIS DO SISTEMA
// -----------------------------------------------------------
echo "window.SISTEMA_VERSAO = '" . VERSAO_ATUAL . "';\n";
echo "window.SISTEMA_PATH = '" . BASE_PATH . "';\n";

echo "window.SISTEMA_CONFIG = {
    versao: '" . VERSAO_ATUAL . "',
    caminhoBase: '" . BASE_PATH . "',
    caminhos: {
        css: '" . BASE_PATH . "css/',
        js:  '" . BASE_PATH . "js/',
        php: '" . BASE_PATH . "php/'
    }
};\n\n";

// -----------------------------------------------------------
// 📌 LISTA DE ARQUIVOS JS OBRIGATÓRIOS (AUDITÁVEL)
// -----------------------------------------------------------
echo "window.SISTEMA_JS_REQUIRED = [
    'controle_pausa.js',
    'interface_botoes.js',
    'status_cards.js',
    'acoes_operador.js',
    'notificacoes_pausa.js',
    'expiracao_pausa.js',
    'inicializacao.js'
];\n\n";

// -----------------------------------------------------------
// 💬 AVISO FINAL
// -----------------------------------------------------------
echo "console.log('%c[Setup] Ambiente inicializado com sucesso.', 
      'color:#00ff88;font-weight:bold;');\n";
?>
