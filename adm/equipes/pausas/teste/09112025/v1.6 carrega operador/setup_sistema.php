<?php
// ============================================================
// setup_sistema.php - Autodetecção de versão e carregamento automático
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
// 🚀 CARREGAMENTO AUTOMÁTICO DOS SCRIPTS (EVITA DUPLICAÇÃO)
// ============================================================
echo "(function() {\n";
echo "  // Evitar duplicação\n";
echo "  if (window.SISTEMA_SCRIPTS_CARREGADOS) {\n";
echo "    console.log('🔄 Scripts já carregados anteriormente');\n";
echo "    return;\n";
echo "  }\n";
echo "  window.SISTEMA_SCRIPTS_CARREGADOS = true;\n\n";

echo "  // Carregar scripts automaticamente\n";
echo "  const versao = window.SISTEMA_VERSAO;\n";
echo "  const baseJS = window.SISTEMA_CONFIG.caminhos.js;\n";
echo "  const scripts = [\n";
echo "    'inicializacao.js',\n";
echo "    'main.js',\n";
echo "    'equipes_operadores.js', \n";
echo "    'controle_pausa.js',\n";
echo "    'diagnostico_sistema.js',\n";
echo "    'integracao_ext.js',\n";
echo "    'integracao_fila.js'\n";
echo "  ];\n\n";

echo "  console.log('🚀 Carregando scripts automaticamente: ' + versao);\n\n";

echo "  scripts.forEach(function(arquivo) {\n";
echo "    const script = document.createElement('script');\n";
echo "    script.src = baseJS + arquivo + '?v=' + versao + '&t=' + Date.now();\n";
echo "    script.defer = true;\n";
echo "    \n";
echo "    script.onload = function() {\n";
echo "      console.log('✅ ' + arquivo + ' carregado');\n";
echo "    };\n";
echo "    \n";
echo "    script.onerror = function() {\n";
echo "      console.error('❌ Falha ao carregar: ' + arquivo);\n";
echo "    };\n";
echo "    \n";
echo "    document.head.appendChild(script);\n";
echo "  });\n\n";

echo "  // Inicializar sistema após carregamento\n";
echo "  setTimeout(function() {\n";
echo "    if (typeof inicializarSistema === 'function') {\n";
echo "      console.log('🎯 Inicializando sistema...');\n";
echo "      inicializarSistema();\n";
echo "    } else {\n";
echo "      console.log('⏳ Aguardando sistema carregar...');\n";
echo "      // Tentar novamente após 2 segundos\n";
echo "      setTimeout(function() {\n";
echo "        if (typeof inicializarSistema === 'function') {\n";
echo "          inicializarSistema();\n";
echo "        } else {\n";
echo "          console.error('❌ Sistema não carregou corretamente');\n";
echo "        }\n";
echo "      }, 2000);\n";
echo "    }\n";
echo "  }, 1000);\n";
echo "})();\n\n";

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
echo "  console.log('%c🟢 Setup carregado com sucesso!', 'color:#00ff88;font-weight:bold;');\n";
echo "};\n\n";

echo "// ✅ Setup e scripts carregados automaticamente (versão: " . VERSAO_ATUAL . ")\n";
echo "console.log('%c✅ Setup completo — sistema pronto para inicialização.', 'color:#00ff88;font-weight:bold;');\n";

?>