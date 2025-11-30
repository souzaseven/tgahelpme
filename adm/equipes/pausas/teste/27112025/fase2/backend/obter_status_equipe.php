<?php
// ============================================================
// obter_status_equipe.php
// Retorna o status completo da equipe para o painel.js
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php"; // arquivo na mesma pasta

// ------------------------------------------------------------
// Validar entrada
// ------------------------------------------------------------
$equipe = $_GET['equipe'] ?? '';
$equipe = trim($equipe);

if ($equipe === '') {
    respostaJSON([
        "success" => false,
        "erro"    => "Equipe não informada."
    ]);
}

// ------------------------------------------------------------
// Consulta operadores da equipe
// ------------------------------------------------------------
try {

    $sql = $pdo->prepare("
        SELECT 
            id,
            nome_usuario AS nome,
            equipe,
            status,
            posicao_fila,
            inicio_pausa,
            inicio_espera,
            TIMESTAMPDIFF(SECOND, inicio_pausa,  NOW()) AS tempo_pausa_seg,
            TIMESTAMPDIFF(SECOND, inicio_espera, NOW()) AS tempo_espera_seg
        FROM controle_pausa
        WHERE equipe = :equipe
        ORDER BY nome_usuario ASC
    ");

    $sql->execute([":equipe" => $equipe]);
    $dbOperadores = $sql->fetchAll();

    if (!$dbOperadores) {
        respostaJSON([
            "success"   => true,
            "equipe"    => $equipe,
            "operadores"=> [],
            "mensagem"  => "Nenhum operador encontrado para essa equipe."
        ]);
    }

    // ------------------------------------------------------------
    // Normalização dos operadores
    // ------------------------------------------------------------
    $operadores = [];
    foreach ($dbOperadores as $linha) {

        // Normaliza status
        $status = strtolower(trim($linha["status"]));
        if (!in_array($status, ["online", "offline", "pausa", "espera"])) {
            $status = "offline";
        }

        // Tempo de pausa
        $tp = $linha["tempo_pausa_seg"];
        $tempoPausa = is_null($tp) || $tp < 0 ? 0 : (int)$tp;

        // Tempo de espera
        $te = $linha["tempo_espera_seg"];
        $tempoEspera = is_null($te) || $te < 0 ? 0 : (int)$te;

        // Posição na fila
        $posFila = $linha["posicao_fila"];
        $posFila = is_null($posFila) ? null : (int)$posFila;

        $operadores[] = [
            "id"               => (int)$linha["id"],
            "nome"             => $linha["nome"],
            "equipe"           => $linha["equipe"],
            "status"           => $status,
            "posicao_fila"     => $posFila,
            "tempo_pausa_seg"  => $tempoPausa,
            "tempo_espera_seg" => $tempoEspera,
        ];
    }

    respostaJSON([
        "success"    => true,
        "equipe"     => $equipe,
        "operadores" => $operadores
    ]);


} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao obter status da equipe.",
        "detalhe" => $e->getMessage()
    ]);
}
