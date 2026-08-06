<?php
// database/criar_tabelas.php
require_once __DIR__ . '/../conexao.php';

// Exclui a tabela antiga se existir
$pdo->exec("DROP TABLE IF EXISTS membros");

// Cria a nova tabela com prefixo renascer_
$sql = "CREATE TABLE IF NOT EXISTS renascer_menbros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    data_nascimento DATE NOT NULL,
    telefone VARCHAR(20),
    endereco TEXT,
    data_conversao DATE,
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    observacoes TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo 'Tabela renascer_menbros criada com sucesso!<br>';

    // Inserir aniversariantes fornecidos
    $aniversariantes = [
        // Janeiro
        ['Ana Paula', '01-06'],
        ['Leandro', '01-09'],
        // Fevereiro
        ['Maximiliano', '02-15'],
        ['Yasmin Barbosa', '02-16'],
        // Março
        ['Juscélia', '03-12'],
        ['Andreia Buck', '03-13'],
        ['Renan', '03-13'],
        // Abril
        ['Vagner', '04-13'],
        ['Cida Lima', '04-27'],
        // Maio
        ['Anderson Assakura', '05-04'],
        ['Samuel', '05-05'],
        ['Orlando', '05-13'],
        ['Caio', '05-13'],
        ['Adriana', '05-15'],
        ['Rosimar', '05-20'],
        ['Cristiane', '05-23'],
        ['Roney', '05-26'],
        // Junho
        ['Maria Cicera', '06-04'],
        ['M.Jessica', '06-13'],
        ['Maurício', '06-21'],
        ['Adilson', '06-25'],
        // Julho
        ['Quezia', '07-06'],
        ['Maiara Santos', '07-09'],
        ['Lucas Eduardo', '07-23'],
        // Agosto
        ['Deuzinete', '08-01'],
        ['Anderson', '08-09'],
        ['Dolores', '08-16'],
        ['Elizabete', '08-31'],
        // Setembro
        ['Vicente', '09-03'],
        ['José Gabriel', '09-15'],
        ['Otanielson', '09-21'],
        ['Silvana', '09-26'],
        // Outubro
        ['Ricardo', '10-16'],
        ['Edvaldo', '10-28'],
        ['Genaildo', '10-30'],
        // Novembro
        ['Jose Antonio', '11-14'],
        ['Sandra Aparecida', '11-15'],
        ['Mirelle', '11-20'],
        ['Antônio Francisco', '11-23'],
        ['Johnatan', '11-26'],
        // Dezembro
        ['Carlos José', '12-06'],
        ['Fábio Junior', '12-12'],
        ['Anderlúcia', '12-23'],
        ['Levi', '12-24'],
        ['Ronilson', '12-24'],
    ];
    $stmt = $pdo->prepare("INSERT INTO renascer_menbros (nome_completo, data_nascimento, status, data_cadastro) VALUES (?, ?, 'ativo', NOW())");
    $ano = date('Y');
    foreach ($aniversariantes as $a) {
        // Usa o ano atual para facilitar testes e relatórios
        $data = $ano . '-' . $a[1];
        $stmt->execute([$a[0], $data]);
    }
    echo 'Aniversariantes inseridos!';
} catch (PDOException $e) {
    echo 'Erro ao criar tabela: ' . htmlspecialchars($e->getMessage());
}
