<?php
require_once "conexao.php";
header("Content-Type: application/json; charset=utf-8");

/* ============================================================
   CAPTURA E VALIDAÇÃO
============================================================ */
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "erro"    => "ID não enviado ou inválido",
        "debug"   => $_POST
    ]);
    exit;
}

/* ============================================================
   EXECUTAR ATUALIZAÇÃO
============================================================ */
try {

    $sql = $pdo->prepare("
        UPDATE controle_pausa
        SET 
            status = 'ativo',
            inicio_pausa = NULL,
            inicio_espera = NULL,
            posicao_fila = NULL,
            notificacao_enviada = 0
        WHERE id = :id
        LIMIT 1
    ");

    $sql->execute([":id" => $id]);

    if ($sql->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "erro"    => "Nenhum registro atualizado. ID inexistente?",
            "id"      => $id
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "msg"     => "Status atualizado para ATIVO",
        "id"      => $id
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
