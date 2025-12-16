<?php
// ============================================================
// iniciar_pausa.php — FASE 9
// Só permite pausa se:
//  - operador existe
//  - é da equipe
//  - está em ESPERA
//  - é o PRIMEIRO da fila (posicao_fila = 1)
//  - existe vaga de pausa (máx 2 por equipe)
// ============================================================
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    respostaJSON([
        "success" => false,
        "erro"    => "ID não informado."
    ]);
}

// ------------------------------------------------------------
// 1) Carrega operador
// ------------------------------------------------------------
$sqlOp = $pdo->prepare("
    SELECT equipe, status, posicao_fila
    FROM controle_pausa
    WHERE id = :id
    LIMIT 1
");
$sqlOp->execute([":id" => $id]);
$op = $sqlOp->fetch(PDO::FETCH_ASSOC);

if (!$op) {
    respostaJSON([
        "success" => false,
        "erro"    => "Operador não encontrado."
    ]);
}

$equipe       = $op["equipe"];
$statusAtual  = strtolower(trim($op["status"] ?? ""));
$posicaoFila  = (int)($op["posicao_fila"] ?? 0);

// ------------------------------------------------------------
// 2) Verifica limite de pausas (máx 2)
// ------------------------------------------------------------
$sqlQtd = $pdo->prepare("
    SELECT COUNT(*) 
    FROM controle_pausa
    WHERE status = 'pausa'
      AND equipe = :eq
");
$sqlQtd->execute([":eq" => $equipe]);
$ocupadas = (int)$sqlQtd->fetchColumn();

if ($ocupadas >= 2) {
    respostaJSON([
        "success" => false,
        "erro"    => "Limite de pausas atingido (máximo 2 por equipe)."
    ]);
}

// ------------------------------------------------------------
// 3) Regra nova: só o PRIMEIRO da fila pode entrar em pausa
// ------------------------------------------------------------
if ($statusAtual !== "espera") {
    respostaJSON([
        "success" => false,
        "erro"    => "Você precisa estar na fila de espera para entrar em pausa."
    ]);
}

if ($posicaoFila !== 1) {
    respostaJSON([
        "success" => false,
        "erro"    => "Apenas o primeiro da fila pode entrar em pausa."
    ]);
}

// ------------------------------------------------------------
// 4) Atualiza para PAUSA (remove da fila)
// ------------------------------------------------------------
$sql = $pdo->prepare("
    UPDATE controle_pausa
    SET status       = 'pausa',
        inicio_pausa = NOW(),
        posicao_fila              = NULL,
        whatsapp_limite_enviado   = 0
    WHERE id = :id
");
$sql->execute([":id" => $id]);

// ------------------------------------------------------------
// 5) NORMALIZAR FILA DA EQUIPE (1,2,3,...) APÓS SAÍDA DO 1º
// ------------------------------------------------------------
try {
    $sel = $pdo->prepare("
        SELECT id
        FROM controle_pausa
        WHERE equipe = :eq
          AND status = 'espera'
        ORDER BY posicao_fila ASC, id ASC
    ");
    $sel->execute([":eq" => $equipe]);
    $fila = $sel->fetchAll(PDO::FETCH_COLUMN);

    if ($fila) {
        $pos = 1;
        $upd = $pdo->prepare("
            UPDATE controle_pausa
            SET posicao_fila = :pos
            WHERE id = :id
        ");

        foreach ($fila as $opId) {
            $upd->execute([
                ":pos" => $pos++,
                ":id"  => $opId
            ]);
        }
    }
} catch (Exception $e) {
    // se der erro aqui, não quebra a pausa, só deixa a fila como estava
    // (se quiser logar em arquivo/tabela, pode usar $e->getMessage())
}

// ------------------------------------------------------------
// 6) RESPOSTA
// ------------------------------------------------------------
respostaJSON([
    "success" => true
]);
