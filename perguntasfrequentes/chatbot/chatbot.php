<?php
header('Content-Type: application/json');

// Conexão com o banco
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao conectar no banco de dados']);
    exit;
}

// Leitura do JSON recebido
$input = json_decode(file_get_contents("php://input"), true);
$pergunta = trim($input['pergunta'] ?? '');

if ($pergunta === '') {
    echo json_encode(['resposta' => 'Pergunta vazia. Por favor, envie uma pergunta válida.']);
    exit;
}

// Busca por pergunta similar já respondida
$stmt = $pdo->prepare("SELECT resposta FROM perguntas WHERE pergunta LIKE :pergunta AND resposta IS NOT NULL AND resposta != '' LIMIT 1");
$stmt->execute([':pergunta' => "%$pergunta%"]);
$respostaExistente = $stmt->fetchColumn();

if ($respostaExistente) {
    echo json_encode(['resposta' => $respostaExistente]);
    exit;
}

// Se não encontrou, grava a nova pergunta
$stmt = $pdo->prepare("INSERT INTO perguntas (pergunta, resposta, fonte, data) VALUES (:pergunta, NULL, NULL, NOW())");
$stmt->execute([':pergunta' => $pergunta]);

// Cria um link de pesquisa no Google
$pesquisaGoogle = urlencode($pergunta);
$resposta = 'Ainda não tenho a resposta para isso. Sua pergunta foi registrada e será analisada por um atendente.';
$resposta .= '<br><br>Enquanto isso, você pode pesquisar no Google: ';
$resposta .= '<a href="https://www.google.com/search?q=' . $pesquisaGoogle . '" target="_blank" style="color: var(--accent-color);">Clique aqui para pesquisar sobre "' . htmlspecialchars($pergunta) . '"</a>';

echo json_encode(['resposta' => $resposta]);