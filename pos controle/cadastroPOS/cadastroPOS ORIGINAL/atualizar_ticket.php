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
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (empty($data['id']) || empty($data['ticket']) || empty($data['empresa']) || 
        empty($data['adquirente']) || empty($data['modelo']) || empty($data['solicitacao'])) {
        echo json_encode(['success' => false, 'error' => 'Campos obrigatórios não preenchidos']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE ticketsmarts SET 
                            ticket = :ticket,
                            empresa = :empresa,
                            cnpj = :cnpj,
                            cod_cliente_tga = :cod_cliente_tga,
                            quant_smart = :quant_smart,
                            adquirente = :adquirente,
                            modelo = :modelo,
                            solicitacao = :solicitacao,
                            anotacao = :anotacao,
                            monitor = :monitor,
                            finalizado = :finalizado,
                            data_atualizacao = NOW()
                          WHERE id = :id");

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
        ':finalizado' => $data['finalizado'] ?? 0,
        ':id' => $data['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Nenhum registro atualizado']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>