<?php
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

    if (!isset($data['id']) || !is_numeric($data['id']) || 
        !isset($data['finalizado']) || !in_array($data['finalizado'], [0, 1])) {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE ticketsmarts 
                          SET finalizado = :finalizado, 
                              data_atualizacao = NOW() 
                          WHERE id = :id");
    
    $stmt->execute([
        ':finalizado' => (int)$data['finalizado'],
        ':id' => (int)$data['id']
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Ticket não encontrado']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>