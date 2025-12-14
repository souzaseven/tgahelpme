<?php
// ============================================================
// consultar_posicao_fila.php — FASE 9 (VERSÃO CORRIGIDA)
// Retorna posição na fila + vagas de pausa
// ============================================================
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// ------------------------------------------------------------
// 1) Validar entrada
// ------------------------------------------------------------
$operador_id = intval($_POST["operador_id"] ?? 0);
$equipe      = trim($_POST["equipe"] ?? "");

if ($operador_id <= 0 || $equipe === "") {
    echo json_encode([
        "success" => false,
        "erro"    => "Dados inválidos para consulta da fila."
    ]);
    exit;
}

try {

    // --------------------------------------------------------
    // 2) Buscar fila completa para corrigir NULLs
    // --------------------------------------------------------
    // Aqui pegamos a fila ordenada corretamente:
    //  - NULL vira 999999 → nunca fica no topo
    //  - Sempre fica estável
    // --------------------------------------------------------
    $sqlFila = $pdo->prepare("
        SELECT 
            id,
            COALESCE(posicao_fila, 999999) AS pos
        FROM controle_pausa
        WHERE equipe = :eq
          AND status = 'espera'
        ORDER BY pos ASC, id ASC
    ");
    $sqlFila->execute([":eq" => $equipe]);
    $fila = $sqlFila->fetchAll(PDO::FETCH_ASSOC);

    // Encontrar posição REAL (1,2,3...)
    $posicaoReal = null;
    $contador = 1;

    foreach ($fila as $row) {
        if ($row["id"] == $operador_id) {
            $posicaoReal = $contador;
            break;
        }
        $contador++;
    }

    if ($posicaoReal === null) {
        echo json_encode([
            "success" => false,
            "erro"    => "Operador não está na fila de espera."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // 3) Número de pausas ativas (máximo = 2)
    // --------------------------------------------------------
    $q = $pdo->prepare("
        SELECT COUNT(*)
        FROM controle_pausa
        WHERE equipe = :eq
          AND status = 'pausa'
    ");
    $q->execute([":eq" => $equipe]);

    $ocupadas = (int)$q->fetchColumn();
    $vagas    = max(0, 2 - $ocupadas);

    // --------------------------------------------------------
    // 4) Resposta final
    // --------------------------------------------------------
    echo json_encode([
        "success"     => true,
        "posicao"     => $posicaoReal,
        "vagas_pausa" => $vagas
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao consultar fila.",
        "detalhe" => $e->getMessage()
    ]);
}
