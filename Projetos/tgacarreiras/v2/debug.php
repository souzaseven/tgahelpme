<?php
/* ============================================================
   debug.php — Coloque na mesma pasta do sugestoes/index.php
   Acesse pelo navegador para ver o erro exato.
   REMOVA APÓS RESOLVER O PROBLEMA!
============================================================ */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#111;color:#0f0;padding:2rem;font-family:monospace;font-size:14px'>";

echo "╔══════════════════════════════════════╗\n";
echo "║   TGA Carreiras — Debug Info         ║\n";
echo "╚══════════════════════════════════════╝\n\n";

echo "📂 __DIR__: " . __DIR__ . "\n";
echo "📄 Script: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "🌐 URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "🐘 PHP: " . phpversion() . "\n\n";

// Testar caminhos do conexao.php
$caminhos = [
    __DIR__ . '/../backend/conexao.php',
    __DIR__ . '/../../backend/conexao.php',
    __DIR__ . '/../../../backend/conexao.php',
    __DIR__ . '/backend/conexao.php',
    dirname(__DIR__) . '/backend/conexao.php',
];

echo "── Buscando conexao.php ──────────────\n\n";
$encontrado = false;
foreach ($caminhos as $c) {
    $existe = file_exists($c);
    $real = $existe ? realpath($c) : 'N/A';
    $icon = $existe ? '✅' : '❌';
    echo "$icon $c\n";
    if ($existe) {
        echo "   → Real: $real\n";
        $encontrado = $c;
    }
}

echo "\n── Testando conexão BD ─────────────────\n\n";
if ($encontrado) {
    echo "🔗 Usando: $encontrado\n\n";
    try {
        include_once $encontrado;
        
        if (isset($pdo) && $pdo instanceof PDO) {
            echo "✅ PDO conectado!\n";
            echo "   MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
            
            // Testar criação de tabela
            echo "\n── Testando tabelas ────────────────────\n\n";
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                echo "📋 Tabelas existentes: " . implode(', ', $tables) . "\n";
                
                $temSugestoes = in_array('sugestoes', $tables);
                echo ($temSugestoes ? '✅' : '⚠️') . " Tabela 'sugestoes': " . ($temSugestoes ? 'Existe' : 'NÃO existe') . "\n";
                
                $temComentarios = in_array('sugestoes_comentarios', $tables);
                echo ($temComentarios ? '✅' : '⚠️') . " Tabela 'sugestoes_comentarios': " . ($temComentarios ? 'Existe' : 'NÃO existe') . "\n";
                
                $temHistorico = in_array('sugestoes_historico', $tables);
                echo ($temHistorico ? '✅' : '⚠️') . " Tabela 'sugestoes_historico': " . ($temHistorico ? 'Existe' : 'NÃO existe') . "\n";
                
            } catch (Throwable $e) {
                echo "❌ Erro nas tabelas: " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠️ Arquivo carregado mas \$pdo não existe.\n";
            echo "   Variáveis disponíveis: " . implode(', ', array_keys(get_defined_vars())) . "\n";
            if (isset($conexao)) echo "   ℹ️ Encontrada variável \$conexao (possível mysqli)\n";
        }
    } catch (Throwable $e) {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
        echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
} else {
    echo "❌ Nenhum conexao.php encontrado!\n";
    echo "   Verifique a estrutura de pastas do projeto.\n";
}

echo "\n── Extensões PHP ───────────────────────\n\n";
$exts = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo'];
foreach ($exts as $ext) {
    echo (extension_loaded($ext) ? '✅' : '❌') . " $ext\n";
}

echo "\n── Testando inclusão do sugestoes ──────\n\n";
$indexFile = __DIR__ . '/index.php';
if (file_exists($indexFile) && basename($indexFile) !== basename(__FILE__)) {
    echo "📄 index.php encontrado: " . realpath($indexFile) . "\n";
    echo "   Tamanho: " . filesize($indexFile) . " bytes\n";
    
    // Verificar sintaxe
    $output = [];
    $ret = 0;
    @exec("php -l " . escapeshellarg($indexFile) . " 2>&1", $output, $ret);
    if ($ret === 0) {
        echo "✅ Sintaxe PHP OK\n";
    } else {
        echo "❌ Erro de sintaxe:\n   " . implode("\n   ", $output) . "\n";
    }
} else {
    echo "⚠️ index.php não encontrado na mesma pasta\n";
}

echo "\n══════════════════════════════════════════\n";
echo "Se tudo estiver ✅ acima, o problema pode\n";
echo "ser de permissão ou .htaccess.\n";
echo "══════════════════════════════════════════\n";
echo "</pre>";