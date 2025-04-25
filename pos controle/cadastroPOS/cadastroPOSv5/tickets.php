<?php
header('Content-Type: application/json');

$host = '108.167.151.50'; 
$dbname = 'tgamea80_SUPORTE';
$user = 'tgamea80_tgamea80';
$password = 'anderson@2250';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT 
        id, 
        ticket, 
        empresa, 
        cnpj, 
        cod_cliente_tga, 
        quant_smart, 
        adquirente, 
        modelo, 
        solicitacao, 
        anotacao, 
        monitor, 
        status, 
        imagem, 
        data_criacao, 
        data_atualizacao,
        criado_por,
        atualizado_por
        FROM ticketsmarts
        ORDER BY data_atualizacao DESC, data_criacao DESC");

    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tickets);

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}