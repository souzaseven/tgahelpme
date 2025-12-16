<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../../backend/conexao.php";

$equipe  = $_GET["equipe"] ?? null;
$destino = $_GET["para"]   ?? "todos";
$meuId   = $_GET["meu_id"] ?? null;

if (!$equipe || !$meuId) {
    echo json_encode([
        "mensagens" => [],
        "naoLidasPorContato" => []
    ]);
    exit;
}

try {

    // === 1. Buscar mensagens ===
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

    if ($destino !== "todos") {
        $sql .= "
            AND (
                (de_id = :eu AND para_id = :ele) OR
                (de_id = :ele AND para_id = :eu)
            )
        ";
        $params[":eu"]  = intval($meuId);
        $params[":ele"] = intval($destino);
    }

    $sql .= " ORDER BY id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $msgs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // === 2. Buscar leitura real do banco (controle de leitura) ===
    $stmtLeituras = $pdo->prepare("
        SELECT contato_id, ultimo_id_lido
        FROM chat_leituras_controle_pausa
        WHERE operador_id = ?
    ");
    $stmtLeituras->execute([intval($meuId)]);
    $leituras = $stmtLeituras->fetchAll(PDO::FETCH_KEY_PAIR); // [contato_id => ultimo_id_lido]

    // === 3. Calcular não lidas por contato ===
    $naoLidas = [];

    foreach ($msgs as $m) {
        $deId = intval($m["de_id"]);
        $paraId = intval($m["para_id"]);
        $msgId = intval($m["id"]);

        if ($paraId === intval($meuId)) {
            $ultimoLido = intval($leituras[$deId] ?? 0);
            if ($msgId > $ultimoLido) {
                if (!isset($naoLidas[$deId])) {
                    $naoLidas[$deId] = 0;
                }
                $naoLidas[$deId]++;
            }
        }
    }

    // === 4. Retornar resposta final ===
    echo json_encode([
        "mensagens" => $msgs,
        "naoLidasPorContato" => $naoLidas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "erro" => true,
        "mensagem" => $e->getMessage()
    ]);
}
