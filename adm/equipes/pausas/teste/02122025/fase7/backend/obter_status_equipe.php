<?php
// ============================================================
// obter_status_equipe.php — FASE 6 (oficial)
// Retorna listas separadas: pausa, fila, equipe completa
// Compatível com painel.js Fase 6
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ============================================================
// CAPTURA DA EQUIPE (POST — Fase 6 usa POST!)
// ============================================================
$equipe = trim($_POST["equipe"] ?? "");

if ($equipe === "") {
    respostaJSON([
        "success" => false,
        "erro"    => "Equipe não informada."
    ]);
}

// ============================================================
// CONSULTA OPERADORES DA EQUIPE
// ============================================================
try {

    $sql = $pdo->prepare("
        SELECT 
            id,
            nome_usuario AS nome,
            status
        FROM controle_pausa
        WHERE equipe = :equipe
        ORDER BY nome_usuario ASC
    ");

    $sql->execute([":equipe" => $equipe]);
    $db = $sql->fetchAll(PDO::FETCH_ASSOC);

    if (!$db) {
        respostaJSON([
            "success"        => true,
            "pausa"          => [],
            "fila"           => [],
            "equipe_completa"=> []
        ]);
    }

    // ============================================================
    // ORGANIZAR EM LISTAS DA FASE 6
    // ============================================================
    $listaPausa = [];
    $listaFila  = [];
    $equipeCompleta = [];

    foreach ($db as $op) {

        $status = strtolower(trim($op["status"]));

        // Normaliza conforme FASE 6
        if (!in_array($status, ["ativo", "pausa", "espera"])) {
            $status = "ativo";
        }

        $registro = [
            "id"     => (int)$op["id"],
            "nome"   => $op["nome"],
            "status" => $status
        ];

        // ► Montagem das listas separadas
        $equipeCompleta[] = $registro;

        if ($status === "pausa") {
            $listaPausa[] = $registro;
        }

        if ($status === "espera") {
            $listaFila[] = $registro;
        }
    }

    // ============================================================
    // RETORNO FINAL (FORMATO FASE 6)
    // ============================================================
    respostaJSON([
        "success"         => true,
        "equipe"          => $equipe,
        "pausa"           => $listaPausa,
        "fila"            => $listaFila,
        "equipe_completa" => $equipeCompleta
    ]);

} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao obter status da equipe.",
        "detalhe" => $e->getMessage()
    ]);
}
