<?php
// ============================================================
// voltar_online.php — Retorna operador ao status ATIVO
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// --------------------------------------------
// VALIDAR ID
// --------------------------------------------
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    respostaJSON([
        "success" => false,
        "erro"    => "ID não informado ou inválido."
    ]);
}

// --------------------------------------------
// BUSCAR OPERADOR
// --------------------------------------------
$sql = $pdo->prepare("
    SELECT nome_usuario, equipe, status
    FROM controle_pausa
    WHERE id = :id
    LIMIT 1
");
$sql->execute([":id" => $id]);
$op = $sql->fetch();

if (!$op) {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador não encontrado."
    ]);
}

$statusAtual = strtolower($op["status"]);

// Não deixar voltar ativo direto se está em pausa
if ($statusAtual === "pausa") {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador está em pausa. Use sair_pausa.php antes."
    ]);
}

// --------------------------------------------
// ATUALIZAR PARA ATIVO
// --------------------------------------------
$sqlUpd = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status               = 'ativo',
        inicio_pausa         = NULL,
        inicio_espera        = NULL,
        posicao_fila         = NULL,
        notificacao_enviada  = 0
    WHERE id = :id
    LIMIT 1
");

$sqlUpd->execute([":id" => $id]);

// --------------------------------------------
// RESPOSTA FINAL
// --------------------------------------------
respostaJSON([
    "success"  => true,
    "mensagem" => "Operador retornou ao status ATIVO.",
    "operador" => $op["nome_usuario"],
    "equipe"   => $op["equipe"]
]);
