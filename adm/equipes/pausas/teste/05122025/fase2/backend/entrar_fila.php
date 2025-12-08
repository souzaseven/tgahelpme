<?php
// ============================================================
// entrar_fila.php — Coloca operador na fila da sua equipe
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// --------------------------------------------
// VALIDAR ID
// --------------------------------------------
$id = $_POST['id'] ?? null;
if (!$id) {
    respostaJSON(["success" => false, "erro" => "ID não informado."]);
}

// --------------------------------------------
// BUSCAR OPERADOR
// --------------------------------------------
$sql = $pdo->prepare("SELECT nome_usuario, equipe, status FROM controle_pausa WHERE id = :id");
$sql->execute([":id" => $id]);
$op = $sql->fetch();

if (!$op) {
    respostaJSON(["success" => false, "erro" => "Operador não encontrado."]);
}

$equipe = $op["equipe"];
$statusAtual = strtolower($op["status"]);

// --------------------------------------------
// IMPEDIR ENTRAR NA FILA SE JÁ ESTIVER EM FILA OU PAUSA
// --------------------------------------------
if ($statusAtual === "espera") {
    respostaJSON(["success" => true, "mensagem" => "Já está na fila."]);
}

if ($statusAtual === "pausa") {
    respostaJSON(["success" => false, "erro" => "Você está em pausa."]);
}

// --------------------------------------------
// CALCULAR PRÓXIMA POSIÇÃO DA FILA (SOMENTE DA EQUIPE)
// --------------------------------------------
$sqlPos = $pdo->prepare("
    SELECT COALESCE(MAX(posicao_fila), 0) + 1 AS prox
    FROM controle_pausa
    WHERE equipe = :equipe
      AND status = 'espera'
");
$sqlPos->execute([":equipe" => $equipe]);
$proxPos = (int)$sqlPos->fetchColumn();

// --------------------------------------------
// ATUALIZAR OPERADOR PARA ENTRAR NA FILA
// --------------------------------------------
$sqlUp = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status = 'espera',
        posicao_fila = :pos,
        inicio_espera = NOW(),
        inicio_pausa = NULL
    WHERE id = :id
");

$sqlUp->execute([
    ":pos" => $proxPos,
    ":id"  => $id
]);

respostaJSON([
    "success" => true,
    "mensagem" => "Entrou na fila com sucesso.",
    "posicao"  => $proxPos
]);
