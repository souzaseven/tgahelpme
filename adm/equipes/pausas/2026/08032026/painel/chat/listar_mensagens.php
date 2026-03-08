<?php
// ============================================================
// chat/listar_mensagens.php
// Retorna histórico de mensagens com campo "foi_lida"
// Fallback seguro caso chat_leituras_controle_pausa não exista
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

// ------------------------------
// PARÂMETROS
// ------------------------------
$equipe  = $_GET["equipe"]  ?? null;
$destino = $_GET["para"]    ?? "todos";
$meuId   = $_GET["meu_id"]  ?? null;

if (!$equipe) {
    echo json_encode([]);
    exit;
}

// ------------------------------
// MONTA FILTRO PRIVADO
// ------------------------------
$params = [":equipe" => $equipe];
$filtro = "";

if ($destino !== "todos" && $meuId !== null) {
    $filtro = "
        AND (
            (m.de_id = :eu AND m.para_id = :ele) OR
            (m.de_id = :ele AND m.para_id = :eu)
        )
    ";
    $params[":eu"]  = intval($meuId);
    $params[":ele"] = intval($destino);
}

// ------------------------------
// TENTATIVA 1: com foi_lida (requer chat_leituras_controle_pausa)
// ------------------------------
try {
    $sql = "
        SELECT
            m.id,
            m.de_id,
            m.de_nome,
            m.para_id,
            m.mensagem,
            m.data_envio,
            CASE
                WHEN cl.ultimo_id_lido >= m.id THEN 1
                ELSE 0
            END AS foi_lida
        FROM chat_mensagens m
        LEFT JOIN chat_leituras_controle_pausa cl
            ON  cl.operador_id = m.para_id
            AND cl.contato_id  = m.de_id
        WHERE m.equipe = :equipe
        $filtro
        ORDER BY m.id DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($msgs as &$m) {
        $m["foi_lida"] = (bool)$m["foi_lida"];
    }

    echo json_encode(array_reverse($msgs), JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    // ------------------------------
    // TENTATIVA 2: sem foi_lida (fallback se tabela não existir)
    // ------------------------------
    try {
        $sql = "
            SELECT
                id,
                de_id,
                de_nome,
                para_id,
                mensagem,
                data_envio,
                0 AS foi_lida
            FROM chat_mensagens m
            WHERE equipe = :equipe
            $filtro
            ORDER BY id DESC
            LIMIT 50
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($msgs as &$m) {
            $m["foi_lida"] = false;
        }

        echo json_encode(array_reverse($msgs), JSON_UNESCAPED_UNICODE);

    } catch (Exception $e2) {
        echo json_encode([]);
    }
}