<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$host = '108.167.151.50'; 
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dados via POST (FormData)
    $data = $_POST;
    $ticket = $data['ticket'] ?? '';
    $empresa = $data['empresa'] ?? '';
    $solicitacao = $data['solicitacao'] ?? '';
    $adquirente = $data['adquirente'] ?? '';
    $modelo = $data['modelo'] ?? '';
    $status = $data['status'] ?? '';

    if (!$ticket || !$empresa || !$solicitacao || !$adquirente || !$modelo || !$status) {
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios não preenchidos']);
        exit;
    }

    // Processar imagem
    $imagem_nome = null;
    if (isset($_FILES['imagemAnexo']) && $_FILES['imagemAnexo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['imagemAnexo']['name'], PATHINFO_EXTENSION);
        $imagem_nome = uniqid('img_', true) . '.' . $ext;
        $caminho = __DIR__ . '/uploads/' . $imagem_nome;

        if (!move_uploaded_file($_FILES['imagemAnexo']['tmp_name'], $caminho)) {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar a imagem.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO ticketsmarts 
        (ticket, empresa, cnpj, cod_cliente_tga, quant_smart, adquirente, modelo, solicitacao, anotacao, monitor, status, imagem, data_criacao, data_atualizacao) 
        VALUES 
        (:ticket, :empresa, :cnpj, :cod_cliente_tga, :quant_smart, :adquirente, :modelo, :solicitacao, :anotacao, :monitor, :status, :imagem, NOW(), NOW())");

    $stmt->execute([
        ':ticket' => $ticket,
        ':empresa' => $empresa,
        ':cnpj' => $data['cnpj'] ?? null,
        ':cod_cliente_tga' => $data['cod_cliente_tga'] ?? null,
        ':quant_smart' => $data['quant_smart'] ?? 0,
        ':adquirente' => $adquirente,
        ':modelo' => $modelo,
        ':solicitacao' => $solicitacao,
        ':anotacao' => $data['anotacao'] ?? null,
        ':monitor' => $data['monitor'] ?? null,
        ':status' => $status,
        ':imagem' => $imagem_nome
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
