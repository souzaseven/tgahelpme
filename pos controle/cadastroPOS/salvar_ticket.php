<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
file_put_contents('debug.log', print_r($_POST, true) . "\n" . file_get_contents('php://input'), FILE_APPEND);

header('Content-Type: application/json');

$host = '108.167.151.50'; 
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['ticket']) || empty($data['empresa']) || empty($data['solicitacao']) || 
        empty($data['adquirente']) || empty($data['modelo'])) {
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios não preenchidos']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO ticketsmarts 
        (ticket, empresa, cnpj, cod_cliente_tga, quant_smart, adquirente, modelo, solicitacao, anotacao, monitor, finalizado, data_criacao, data_atualizacao) 
        VALUES 
        (:ticket, :empresa, :cnpj, :cod_cliente_tga, :quant_smart, :adquirente, :modelo, :solicitacao, :anotacao, :monitor, :finalizado, NOW(), NOW())");

    $stmt->execute([
        ':ticket' => $data['ticket'],
        ':empresa' => $data['empresa'],
        ':cnpj' => $data['cnpj'] ?? null,
        ':cod_cliente_tga' => $data['cod_cliente_tga'] ?? null,
        ':quant_smart' => $data['quant_smart'] ?? 0,
        ':adquirente' => $data['adquirente'],
        ':modelo' => $data['modelo'],
        ':solicitacao' => $data['solicitacao'],
        ':anotacao' => $data['anotacao'] ?? null,
        ':monitor' => $data['monitor'] ?? null,
        ':finalizado' => $data['finalizado'] ?? 0
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>