<?php
// ============================================================
// sair_fila.php — Retorna operador ao status ATIVO
// FASE 9 (corrigido)
// ------------------------------------------------------------
// - Não deixa sair direto se estiver em pausa
// - Remove operador da fila (posicao_fila = NULL)
// - Normaliza a fila da equipe (1,2,3...) após a saída
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// --------------------------------------------
// VALIDAR ID
// --------------------------------------------
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    respostaJSON([
        "success" => false,
        "erro"    => "ID não informado ou inválido."
    ]);
}

// --------------------------------------------
// BUSCAR OPERADOR
// --------------------------------------------
$sql = $pdo->prepare("
    SELECT nome_usuario, equipe, status
    FROM controle_pausa
    WHERE id = :id
    LIMIT 1
");
$sql->execute([":id" => $id]);
$op = $sql->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador não encontrado."
    ]);
}

$equipe      = $op["equipe"];
$statusAtual = strtolower($op["status"]);

// Não deixar voltar ativo direto se está em pausa
if ($statusAtual === "pausa") {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador está em pausa. Use sair_pausa.php antes."
    ]);
}

// --------------------------------------------
// ATUALIZAR PARA ATIVO (remove da fila)
// --------------------------------------------
$sqlUpd = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status               = 'ativo',
        inicio_pausa         = NULL,
        inicio_espera        = NULL,
        posicao_fila         = NULL,
        notificacao_enviada  = 0
    WHERE id = :id
    LIMIT 1
");

$sqlUpd->execute([":id" => $id]);

// --------------------------------------------
// NORMALIZAR FILA DA EQUIPE (1,2,3,...)
// --------------------------------------------
try {
    $sel = $pdo->prepare("
        SELECT id
        FROM controle_pausa
        WHERE equipe = :equipe
          AND status = 'espera'
        ORDER BY posicao_fila ASC, id ASC
    ");
    $sel->execute([":equipe" => $equipe]);
    $fila = $sel->fetchAll(PDO::FETCH_COLUMN);

    if ($fila) {
        $pos = 1;
        $updFila = $pdo->prepare("
            UPDATE controle_pausa
            SET posicao_fila = :pos
            WHERE id = :id
        ");

        foreach ($fila as $opId) {
            $updFila->execute([
                ":pos" => $pos++,
                ":id"  => $opId
            ]);
        }
    }
} catch (Exception $e) {
    // não quebra a resposta por causa da normalização
    // mas registra erro se quiser logar em outro lugar
}

// --------------------------------------------
// RESPOSTA FINAL
// --------------------------------------------
respostaJSON([
    "success"  => true,
    "mensagem" => "Operador retornou ao status ATIVO.",
    "operador" => $op["nome_usuario"],
    "equipe"   => $equipe
]);
