<?php
// ============================================================
// entrar_fila.php — Coloca operador na fila da sua equipe (FASE 9 CORRIGIDO)
// Correções:
//  ✔ Reorganiza a fila inteira antes de entrar
//  ✔ Garante que novo operador entra SEMPRE como ÚLTIMO
//  ✔ Evita buracos e duplicações
// ============================================================

header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// --------------------------------------------
// VALIDAR ID
// --------------------------------------------
$id = $_POST['id'] ?? null;
if (!$id) {
    respostaJSON(["success" => false, "erro" => "ID não informado."]);
}

// --------------------------------------------
// BUSCAR OPERADOR
// --------------------------------------------
$sql = $pdo->prepare("SELECT nome_usuario, equipe, status FROM controle_pausa WHERE id = :id");
$sql->execute([":id" => $id]);
$op = $sql->fetch();

if (!$op) {
    respostaJSON(["success" => false, "erro" => "Operador não encontrado."]);
}

$equipe = $op["equipe"];
$statusAtual = strtolower($op["status"]);

// --------------------------------------------
// IMPEDIR ENTRAR NA FILA SE JÁ ESTIVER EM FILA OU PAUSA
// --------------------------------------------
if ($statusAtual === "espera") {
    respostaJSON(["success" => true, "mensagem" => "Já está na fila."]);
}

if ($statusAtual === "pausa") {
    respostaJSON(["success" => false, "erro" => "Você está em pausa."]);
}

// ============================================================
// 1) NORMALIZAR FILA DA EQUIPE (evita buracos/duplicações)
// ============================================================
$sel = $pdo->prepare("
    SELECT id
    FROM controle_pausa
    WHERE equipe = :equipe AND status = 'espera'
    ORDER BY posicao_fila ASC, id ASC
");
$sel->execute([":equipe" => $equipe]);
$fila = $sel->fetchAll(PDO::FETCH_COLUMN);

$pos = 1;
$upd = $pdo->prepare("UPDATE controle_pausa SET posicao_fila = :pos WHERE id = :id");
foreach ($fila as $opId) {
    $upd->execute([
        ":pos" => $pos++,
        ":id"  => $opId
    ]);
}

// ============================================================
// 2) RE-CALCULAR ÚLTIMA POSIÇÃO (AGORA GARANTIDA CORRETA)
// ============================================================
$sqlPos = $pdo->prepare("
    SELECT COALESCE(MAX(posicao_fila), 0) + 1
    FROM controle_pausa
    WHERE equipe = :equipe AND status = 'espera'
");
$sqlPos->execute([":equipe" => $equipe]);
$proxPos = (int)$sqlPos->fetchColumn();

// ============================================================
// 3) COLOCAR OPERADOR NO ÚLTIMO DA FILA
// ============================================================
$sqlUp = $pdo->prepare("
    UPDATE controle_pausa
    SET 
        status = 'espera',
        posicao_fila = :pos,
        inicio_espera = NOW(),
        inicio_pausa = NULL
    WHERE id = :id
");
$sqlUp->execute([
    ":pos" => $proxPos,
    ":id"  => $id
]);

respostaJSON([
    "success"   => true,
    "mensagem"  => "Entrou na fila com sucesso.",
    "posicao"   => $proxPos
]);
