<?php
// Ativar exibição de todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== INÍCIO DO SCRIPT ===\n";

// Verificar se o arquivo de conexão existe
if (!file_exists('conexao.php')) {
    die("ERRO: Arquivo conexao.php não encontrado!\n");
}

echo "Arquivo conexao.php encontrado\n";

// Incluir conexão
require 'conexao.php';
echo "Conexão incluída com sucesso\n";

// Dados dos operadores (mantenha o mesmo array que você já tem)
$operadores = [
    // ... (seu array atual de operadores) ...
];

try {
    echo "Iniciando transação...\n";
    $pdo->beginTransaction();
    
    echo "Executando TRUNCATE TABLE...\n";
    $pdo->exec("TRUNCATE TABLE operadores");
    echo "Tabela limpa com sucesso\n";
    
    echo "Preparando statement...\n";
    $stmt = $pdo->prepare("INSERT INTO operadores (nome, lider, fila, link) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . implode(", ", $pdo->errorInfo()));
    }
    
    $contador = 0;
    foreach ($operadores as $operador) {
        echo "Processando: " . $operador['nome'] . "... ";
        
        $result = $stmt->execute([
            $operador['nome'],
            $operador['lider'],
            $operador['fila'],
            $operador['link']
        ]);
        
        if (!$result) {
            throw new Exception("Erro ao inserir " . $operador['nome'] . ": " . implode(", ", $stmt->errorInfo()));
        }
        
        $contador++;
        echo "OK\n";
    }
    
    $pdo->commit();
    echo "\nMigração concluída com sucesso! $contador registros inseridos.\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nERRO: " . $e->getMessage() . "\n";
    echo "Informações do PDO: " . implode(", ", $pdo->errorInfo()) . "\n";
}

echo "=== FIM DO SCRIPT ===\n";
?>