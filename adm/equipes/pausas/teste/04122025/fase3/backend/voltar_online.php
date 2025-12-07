<?php
// ============================================================
// voltar_online.php — Retorna operador ao status ONLINE
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
$sql = $pdo->prepare("
    SELECT nome_usuario, equipe, status 
    FROM controle_pausa 
    WHERE id = :id
");
$sql->execute([":id" => $id]);
$op = $sql->fetch();

if (!$op) {
    respostaJSON(["success" => false, "erro" => "Operador não encontrado."]);
}

// Bloqueia caso esteja em pausa → deve sair da pausa antes
if ($op["status"] === "pausa") {
    respostaJSON([
        "success" => false,
        "erro" => "Operador está em pausa. Use sair_pausa.php antes."
    ]);
}

// --------------------------------------------
// ATUALIZAR STATUS PARA ONLINE
// --------------------------------------------
$sql = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status = 'online',
        inicio_pausa = NULL,
        inicio_espera = NULL,
        posicao_fila = NULL,
        notificacao_enviada = 0
    WHERE id = :id
");

$sql->execute([":id" => $id]);

// --------------------------------------------
// RETORNO FINAL
// --------------------------------------------
respostaJSON([
    "success"  => true,
    "mensagem" => "Operador voltou para online.",
    "operador" => $op["nome_usuario"],
    "equipe"   => $op["equipe"]
]);
