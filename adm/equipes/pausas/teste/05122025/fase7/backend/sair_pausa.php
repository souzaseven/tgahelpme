<?php
// ============================================================
// sair_pausa.php — Operador saindo da PAUSA (vai para ATIVO)
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// --------------------------------------------
// VALIDAR ID
// --------------------------------------------
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

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

// Se não estava em pausa, bloqueia retorno incorreto
if ($op["status"] !== "pausa") {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador não está em pausa."
    ]);
}

// --------------------------------------------
// ATUALIZAR PARA ATIVO
// --------------------------------------------
$sql = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status              = 'ativo',
        inicio_pausa        = NULL,
        inicio_espera       = NULL,
        posicao_fila        = NULL,
        notificacao_enviada = 0
    WHERE id = :id
    LIMIT 1
");

$sql->execute([":id" => $id]);

// --------------------------------------------
// RETORNO FINAL
// --------------------------------------------
respostaJSON([
    "success"  => true,
    "mensagem" => "Operador saiu da pausa e voltou para ATIVO.",
    "operador" => $op["nome_usuario"],
    "equipe"   => $op["equipe"]
]);
