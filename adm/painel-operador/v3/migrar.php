<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== INÍCIO DO SCRIPT DE MIGRAÇÃO ===\n";

require 'conexao.php';
echo "Conexão com o banco estabelecida com sucesso!\n";

// Converter dados do data.js para formato PHP
$operadores = [
    [
        'nome' => "Emerson Hoffmann Cassemiro",
        'lider' => "Alex Sandro Braulio", 
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/23"
    ],
    [
        'nome' => "Jesiane Gabriele Campos da Silva",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/29"
    ],
    [
        'nome' => "João Pedro Alves de Oliveira",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/31"
    ],
    [
        'nome' => "Lindomar Gimenes Junior",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/32"
    ],
    [
        'nome' => "Luiz Henrique Camargo Moura",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/33"
    ],
    [
        'nome' => "Matheus Xavier dos Santos",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/37"
    ],
    [
        'nome' => "Pedro Henrique de Souza Egues",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/41"
    ],
    [
        'nome' => "Rafael Felipe Santos Machado",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/72"
    ],
    [
        'nome' => "Renan Canachiro dos Santos",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/75"
    ],
    [
        'nome' => "Rodrigo de Moraes Ribeiro",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/42"
    ],
    [
        'nome' => "Vinicius D'César Lira Ladeia",
        'lider' => "Alex Sandro Braulio",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/45"
    ],
    [
        'nome' => "Antonio Oliveira",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/20"
    ],
    [
        'nome' => "Anderson de Souza",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/18"
    ],
    [
        'nome' => "Carlos Eduardo Nascimento Silva",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/59"
    ],
    [
        'nome' => "Gabriel Sanini",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/26"
    ],
    [
        'nome' => "Jessica Bergue",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/30"
    ],
    [
        'nome' => "Moisés Vinicius da Silva Moura",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/38"
    ],
    [
        'nome' => "Pablo de Freitas Sanches de Souza",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/40"
    ],
    [
        'nome' => "Suzana Ferreira da Silva Leão",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/43"
    ],
    [
        'nome' => "Uanderson Almeida",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/44"
    ],
    [
        'nome' => "Victor Luan Francisco de Souza",
        'lider' => "Daniel Feix",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/74"
    ],
    [
        'nome' => "Alexsandro Matsushita",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/17"
    ],
    [
        'nome' => "Andrey Mayer",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/19"
    ],
    [
        'nome' => "Daniel Magalhães Batista",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/21"
    ],
    [
        'nome' => "Diogo de Lima Neves",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/22"
    ],
    [
        'nome' => "Felipe Vargas Maldonado de Souza",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/24"
    ],
    [
        'nome' => "Flavio Vinicius da Costa Marchetti",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/25"
    ],
    [
        'nome' => "Igor Henrique Lazaroto",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/27"
    ],
    [
        'nome' => "Matheus Feliphe Silva Siqueira",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/35"
    ],
    [
        'nome' => "Matheus Henrique Moreira",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/36"
    ],
    [
        'nome' => "Wender Domingos de Jesus",
        'lider' => "Willian Pereira Reis",
        'fila' => "Suporte Matriz",
        'link' => "https://tgasistemas.evolux.io/callcenter/agent/edit/46"
    ]
];

try {
    // Verificar estrutura da tabela
    echo "Verificando estrutura da tabela...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM operadores LIKE 'fila'");
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM operadores LIKE 'filas'");
        if ($stmt->rowCount() > 0) {
            echo "Renomeando coluna 'filas' para 'fila'...\n";
            $pdo->exec("ALTER TABLE operadores CHANGE COLUMN filas fila VARCHAR(100) NOT NULL");
        } else {
            echo "Criando coluna 'fila'...\n";
            $pdo->exec("ALTER TABLE operadores ADD COLUMN fila VARCHAR(100) NOT NULL AFTER lider");
        }
    }

    // Configurar transação manual
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
    $pdo->beginTransaction();

    echo "Limpando tabela existente...\n";
    $pdo->exec("TRUNCATE TABLE operadores");

    echo "Preparando inserção de dados...\n";
    $stmt = $pdo->prepare("INSERT INTO operadores (nome, lider, fila, link) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . implode(", ", $pdo->errorInfo()));
    }

    $contador = 0;
    foreach ($operadores as $operador) {
        echo "Inserindo: " . $operador['nome'] . "... ";
        
        $success = $stmt->execute([
            $operador['nome'],
            $operador['lider'],
            $operador['fila'],
            $operador['link']
        ]);
        
        if (!$success) {
            throw new Exception("Erro ao inserir " . $operador['nome'] . ": " . implode(", ", $stmt->errorInfo()));
        }
        
        $contador++;
        echo "OK\n";
    }

    $pdo->commit();
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    
    echo "\nMIGRAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "Total de registros inseridos: $contador\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }
    
    echo "\nERRO NO BANCO DE DADOS:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Info: " . implode(", ", $pdo->errorInfo()) . "\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }
    
    echo "\nERRO GERAL:\n";
    echo $e->getMessage() . "\n";
}

echo "=== FIM DO SCRIPT ===\n";
?>