<?php
include 'conexao.php';

try {
    // Criar tabela se não existir
    $sql = "CREATE TABLE IF NOT EXISTS controle_pausa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE,
        status ENUM('disponivel', 'espera', 'pausa') DEFAULT 'disponivel',
        inicio_espera DATETIME NULL,
        inicio_pausa DATETIME NULL,
        tempo_total_espera INT DEFAULT 0,
        tempo_total_pausa INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    // Inserir participantes
    $participantes = [
        "Anderson de Souza", "Antônio Carlos", "Carlos Eduardo", "Daniel Feix",
        "Heitor Simon", "Igor Gabriel", "Jesse Kalebe", "Jessica Bergue",
        "Lucas Eduardo", "Moisés Vinicius", "Pablo de Freitas", 
        "Suzana Ferreira", "Uanderson Almeida"
    ];
    
    foreach ($participantes as $nome) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO controle_pausa (nome, status) VALUES (?, 'disponivel')");
        $stmt->execute([$nome]);
    }
    
    echo "Banco inicializado com sucesso!";
    
} catch (PDOException $e) {
    die("Erro ao inicializar banco: " . $e->getMessage());
}
?>