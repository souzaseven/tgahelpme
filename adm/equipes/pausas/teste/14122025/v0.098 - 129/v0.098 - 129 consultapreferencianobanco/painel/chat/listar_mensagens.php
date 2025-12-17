<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

// ------------------------------
// PARÂMETROS
// ------------------------------
$equipe  = $_GET["equipe"] ?? null;
$destino = $_GET["para"]   ?? "todos";  // <- bate com chat.js
$meuId   = $_GET["meu_id"] ?? null;     // <- necessário para filtrar privado corretamente

if (!$equipe) {
    echo json_encode([]);
    exit;
}

try {

    // ------------------------------
    // BASE DA QUERY
    // ------------------------------
    $sql = "
        SELECT 
            id,
            de_id,
            de_nome,
            para_id,
            mensagem,
            data_envio
        FROM chat_mensagens
        WHERE equipe = :equipe
    ";

    $params = [":equipe" => $equipe];

    // ----------------------------------------------------
    // FILTRO PRIVADO
    // Só exibe:
    // - mensagens que eu enviei para ele
    // - mensagens que ele enviou para mim
    // ----------------------------------------------------
    if ($destino !== "todos" && $meuId !== null) {

        $sql .= "
            AND (
                (de_id = :eu AND para_id = :ele) OR
                (de_id = :ele AND para_id = :eu)
            )
        ";

        $params[":eu"]  = intval($meuId);
        $params[":ele"] = intval($destino);
    }

    // ------------------------------
    // FINALIZA A QUERY
    // ------------------------------
    $sql .= " ORDER BY id DESC LIMIT 50 ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ------------------------------
    // RETORNO ORGANIZADO
    // ------------------------------
    echo json_encode(
        array_reverse($msgs),
        JSON_UNESCAPED_UNICODE
    );

} catch (Exception $e) {

    echo json_encode([
        "erro" => true,
        "mensagem" => $e->getMessage()
    ]);
}
