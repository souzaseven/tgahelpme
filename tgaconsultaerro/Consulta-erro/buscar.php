<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servidor = "108.167.151.50";
$banco = "tgamea80_MYSUITEATENDIMENTOS";
$usuario = "tgamea80";
$senha = "anderson@2249";

// Conexão com o banco
$conn = new mysqli($servidor, $usuario, $senha, $banco);

if ($conn->connect_error) {
    echo json_encode(["error" => "Erro na conexão: " . $conn->connect_error]);
    exit;
}

// Verifica se a requisição contém o parâmetro 'query'
if (!isset($_POST['query']) || empty(trim($_POST['query']))) {
    echo json_encode(["error" => "Nenhuma consulta fornecida."]);
    exit;
}

// Recebe o texto da busca
$query = trim($_POST['query']);

// Prepara a consulta SQL
$sql = "SELECT problema, solucao FROM at_atendimento WHERE problema LIKE ? OR solucao LIKE ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]);
    exit;
}

$param = "%" . $query . "%";
$stmt->bind_param("ss", $param, $param);

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();

// Verifica se há resultados
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Retorna os resultados como JSON
header('Content-Type: application/json');
echo json_encode($data);

// Fecha a conexão
$conn->close();
?>
