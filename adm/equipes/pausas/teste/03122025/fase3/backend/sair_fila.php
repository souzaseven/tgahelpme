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
$sql = $pdo->prepare("SELECT nome_usuario, equipe, status FROM controle_pausa WHERE id = :id");
$sql->execute([":id" => $id]);
$op = $sql->fetch();

if (!$op) {
    respostaJSON(["success" => false, "erro" => "Operador não encontrado."]);
}

$statusAtual = strtolower($op["status"]);

// Se estiver em pausa, não pode voltar direto
if ($statusAtual === "pausa") {
    respostaJSON([
        "success" => false,
        "erro" => "Operador está em pausa. Use 'sair_pausa' antes."
    ]);
}

// --------------------------------------------
// ATUALIZAÇÃO PARA ONLINE
// --------------------------------------------
$sqlUpd = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status = 'online',
        posicao_fila = NULL,
        inicio_espera = NULL,
        inicio_pausa = NULL,
        notificacao_enviada = 0
    WHERE id = :id
");

$sqlUpd->execute([":id" => $id]);

// --------------------------------------------
// RESPOSTA FINAL
// --------------------------------------------
respostaJSON([
    "success" => true,
    "mensagem" => "Operador agora está ONLINE.",
    "operador" => $op["nome_usuario"],
    "equipe"   => $op["equipe"]
]);
