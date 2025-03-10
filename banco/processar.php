<?php
// Definir configurações de conexão com o banco de dados
$servername = "108.167.151.50"; // IP do servidor MySQL
$username = "sysdba"; // Usuário MySQL
$password = "tgasistemas"; // Senha MySQL
$dbname = "MYSUITEATENDIMENTOS.FDB"; // Nome do banco de dados

// Conectar ao banco de dados utilizando mysqli com checagem de erro
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar se a conexão falhou
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para buscar dados na tabela at_atendimento com base no texto fornecido
function buscarAtendimentos($texto) {
    global $conn;

    // Usar prepared statement para evitar SQL injection
    $stmt = $conn->prepare("SELECT iddataatendimento, bzpdata, problema, solucao, obs 
                            FROM at_atendimento 
                            WHERE problema LIKE ? OR solucao LIKE ? OR obs LIKE ?");
    
    // Adicionando o curinga '%' para realizar busca parcial
    $searchTerm = "%" . $texto . "%"; 
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);

    // Executar a consulta
    $stmt->execute();
    $result = $stmt->get_result();

    // Exibir resultados da consulta
    if ($result->num_rows > 0) {
        echo "<h2>Resultados da Pesquisa</h2>";
        echo "<table><tr><th>ID Atendimento</th><th>Data Atendimento</th><th>Problema</th><th>Solução</th><th>Observações</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>" . $row["iddataatendimento"] . "</td>
                    <td>" . $row["bzpdata"] . "</td>
                    <td>" . $row["problema"] . "</td>
                    <td>" . $row["solucao"] . "</td>
                    <td>" . $row["obs"] . "</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "Nenhum resultado encontrado.";
    }

    // Fechar o statement
    $stmt->close();
}

// Verificar se o formulário de pesquisa foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['texto'])) {
    // Obter texto de pesquisa
    $texto = $_POST['texto'];
    buscarAtendimentos($texto);
}

// Fechar a conexão com o banco
$conn->close();
?>

<!-- Formulário de pesquisa -->
<form method="POST" action="">
    <label for="texto">Pesquisar por Problema, Solução ou Observação:</label>
    <input type="text" id="texto" name="texto" required>
    <button type="submit">Pesquisar</button>
</form>
