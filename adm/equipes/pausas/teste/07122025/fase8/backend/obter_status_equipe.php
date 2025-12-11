<?php
// ============================================================
// obter_status_equipe.php — FASE 6 + FASE 7
// ------------------------------------------------------------
// Retorna listas separadas: pausa, fila, equipe completa
// Compatível com painel.js (Fase 6 + Fila com tempo/posição)
// ------------------------------------------------------------
// Formato de retorno (JSON):
//  {
//    success: true,
//    equipe: "Nome da equipe",
//    pausa: [ { id, nome, status } ],
//    fila:  [ { id, nome, status, posicao_fila, tempo_espera_seg } ],
//    equipe_completa: [ { id, nome, status } ]
//  }
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ============================================================
// Função de resposta (se já existir em outro include, pode remover)
// ============================================================
if (!function_exists("respostaJSON")) {
    function respostaJSON($arr) {
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================
// CAPTURA DA EQUIPE (POST — painel.js usa POST!)
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
            "success"          => true,
            "equipe"           => $equipe,
            "pausa"            => [],
            "fila"             => [],
            "equipe_completa"  => []
        ]);
    }

    // ========================================================
    // ORGANIZAR EM LISTAS (FASE 6 + FASE 7)
    // ========================================================
    $listaPausa       = [];
    $listaFila        = [];
    $equipeCompleta   = [];

    foreach ($db as $op) {

        $status = strtolower(trim($op["status"] ?? ""));

        // Normaliza status para o trio oficial
        if (!in_array($status, ["ativo", "pausa", "espera"])) {
            $status = "ativo";
        }

        // Registro base
        $registro = [
            "id"     => (int) $op["id"],
            "nome"   => $op["nome"],
            "status" => $status
        ];

        // Sempre entra na equipe completa
        $equipeCompleta[] = $registro;

        // Lista de pausa
        if ($status === "pausa") {
            $listaPausa[] = $registro;
        }

        // Lista de fila (FASE 7 — adiciona posição e tempo inicial)
        if ($status === "espera") {

            // posição na fila calculada pela ordem de montagem
            $posicao = count($listaFila) + 1;

            // tempo de espera em segundos
            // Por enquanto começa em 0, o painel.js incrementa a cada segundo.
            $registroFila = $registro;
            $registroFila["posicao_fila"]      = $posicao;
            $registroFila["tempo_espera_seg"]  = 0;

            $listaFila[] = $registroFila;
        }
    }

    // ========================================================
    // RETORNO FINAL
    // ========================================================
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
