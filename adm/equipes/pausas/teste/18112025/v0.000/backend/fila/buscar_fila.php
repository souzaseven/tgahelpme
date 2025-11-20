<?php
require_once "../conexao.php";

header("Content-Type: application/json; charset=utf-8");

$equipe = $_GET['equipe'] ?? "";

if (!$equipe) {
    echo json_encode(["success" => false, "erro" => "Equipe inválida"]);
    exit;
}

try {

    // ==========================
    // BUSCAR FILA
    // ==========================
    $sql = $pdo->prepare("
        SELECT 
            f.id,
            f.operador_id,
            o.nome,
            f.inicio
        FROM controle_pausa_fila f
        INNER JOIN operadores o ON o.id = f.operador_id
        WHERE f.equipe = :eq
        ORDER BY f.inicio ASC
    ");
    $sql->bindValue(":eq", $equipe);
    $sql->execute();

    $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fila as $i => $v) {
        $fila[$i]['posicao'] = $i + 1;
    }

    // ==========================
    // BUSCAR PAUSAS (COM NOME + ID DA PAUSA)
    // ==========================
    $sql2 = $pdo->prepare("
        SELECT 
            p.id AS pausa_id,
            p.operador_id,
            p.inicio,
            o.nome
        FROM controle_pausa_pausas p
        LEFT JOIN operadores o ON o.id = p.operador_id
        WHERE p.equipe = :eq 
          AND p.ativo = 1
        ORDER BY p.inicio ASC
    ");
    $sql2->execute([":eq" => $equipe]);
    $pausas = $sql2->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // CALCULAR VAGAS
    // ==========================
    $vagas = max(0, 2 - count($pausas));

    echo json_encode([
        "success" => true,
        "fila"        => $fila,
        "pausas"      => $pausas,
        "vagas_pausa" => $vagas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "erro" => $e->getMessage()
    ]);
}
