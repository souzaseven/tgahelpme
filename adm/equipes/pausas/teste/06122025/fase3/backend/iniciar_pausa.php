<?php
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

$id = $_POST['id'] ?? null;

if (!$id) respostaJSON(["success" => false, "erro" => "ID não informado."]);

// Só deixa entrar em pausa se NÃO houver mais de 2 ativos
$sqlQtd = $pdo->prepare("
    SELECT COUNT(*) FROM controle_pausa
    WHERE status = 'pausa' 
      AND equipe = (SELECT equipe FROM controle_pausa WHERE id = :id)
");
$sqlQtd->execute([":id" => $id]);
$ocupadas = (int)$sqlQtd->fetchColumn();

if ($ocupadas >= 2) {
    respostaJSON(["success" => false, "erro" => "Limite de pausas atingido."]);
}

$sql = $pdo->prepare("
    UPDATE controle_pausa
    SET status = 'pausa',
        inicio_pausa = NOW(),
        posicao_fila = NULL
    WHERE id = :id
");

$sql->execute([":id" => $id]);

respostaJSON(["success" => true]);
