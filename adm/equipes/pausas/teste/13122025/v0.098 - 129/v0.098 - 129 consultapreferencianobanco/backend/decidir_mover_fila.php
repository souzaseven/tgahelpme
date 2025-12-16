<?php
// ============================================================
// decidir_mover_fila.php — FASE 9 (VERSÃO CORRIGIDA)
// Move operador para SEGUNDO ou ÚLTIMO da fila
// ============================================================
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

$operador_id = intval($_POST["operador_id"] ?? 0);
$equipe      = trim($_POST["equipe"] ?? "");
$acao        = $_POST["acao"] ?? "";

if ($operador_id <= 0 || $equipe === "" || !in_array($acao, ["segundo","ultimo"], true)) {
    echo json_encode([
        "success" => false,
        "erro"    => "Dados inválidos para mudança de posição."
    ]);
    exit;
}

try {

    // --------------------------------------------------------
    // 1) Buscar fila corrigida
    // --------------------------------------------------------
    // Converte NULL para posição gigante → nunca fica no topo
    $sql = $pdo->prepare("
        SELECT 
            id,
            COALESCE(posicao_fila, 999999) AS posicao_fila
        FROM controle_pausa
        WHERE equipe = :eq
          AND status = 'espera'
        ORDER BY posicao_fila ASC, id ASC
    ");
    $sql->execute([":eq" => $equipe]);
    $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

    if (!$fila) {
        echo json_encode([
            "success" => false,
            "erro"    => "Nenhuma fila encontrada."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // 2) Separar operador e demais
    // --------------------------------------------------------
    $alvo = null;
    $outros = [];

    foreach ($fila as $row) {
        if ($row["id"] == $operador_id) {
            $alvo = $row;
        } else {
            $outros[] = $row;
        }
    }

    if (!$alvo) {
        echo json_encode([
            "success" => false,
            "erro"    => "Operador não está na fila."
        ]);
        exit;
    }

    // --------------------------------------------------------
    // 3) Nova ordem
    // --------------------------------------------------------
    $novaOrdem = [];

    if ($acao === "segundo") {

        if (count($outros) > 0) {
            $primeiro = array_shift($outros);
            $novaOrdem[] = $primeiro;
            $novaOrdem[] = $alvo;

            foreach ($outros as $item) {
                $novaOrdem[] = $item;
            }
        } else {
            // só ele na fila
            $novaOrdem[] = $alvo;
        }

    } else if ($acao === "ultimo") {

        foreach ($outros as $item) {
            $novaOrdem[] = $item;
        }
        $novaOrdem[] = $alvo;
    }

    // --------------------------------------------------------
    // 4) Reescrever posições reais (1,2,3,...)
    // --------------------------------------------------------
    $upd = $pdo->prepare("
        UPDATE controle_pausa
        SET posicao_fila = :pos,
            inicio_espera = NOW()
        WHERE id = :id
    ");

    $pos = 1;
    foreach ($novaOrdem as $row) {
        $upd->execute([
            ":pos" => $pos++,
            ":id"  => $row["id"]
        ]);
    }

    echo json_encode([
        "success" => true,
        "msg"     => "Fila atualizada com sucesso."
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro"    => "Erro ao atualizar a fila.",
        "detalhe" => $e->getMessage()
    ]);
}
