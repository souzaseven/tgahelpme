<?php
require_once "conexao.php";
header("Content-Type: application/json; charset=utf-8");

/* ============================================================
   CAPTURA E VALIDAÇÃO
============================================================ */
$id = intval($_POST["id"] ?? 0);

if ($id <= 0) {
    respostaJSON([
        "success" => false,
        "erro"    => "ID não enviado ou inválido"
    ]);
}

/* ============================================================
   FASE 6 — Ao fazer login, o operador fica ATIVO
   E limpa qualquer estado anterior (pausa/fila)
============================================================ */

try {

    $sql = $pdo->prepare("
        UPDATE controle_pausa
        SET 
            status        = 'ativo',
            inicio_pausa  = NULL,
            inicio_espera = NULL,
            posicao_fila  = NULL
        WHERE id = :id
        LIMIT 1
    ");

    $sql->execute([":id" => $id]);

    if ($sql->rowCount() === 0) {
        respostaJSON([
            "success" => false,
            "erro"    => "Nenhum registro atualizado. ID não encontrado.",
            "id"      => $id
        ]);
    }

    respostaJSON([
        "success" => true,
        "msg"     => "Operador definido como ATIVO",
        "id"      => $id
    ]);

} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao definir operador online.",
        "detalhe" => $e->getMessage()
    ]);
}
