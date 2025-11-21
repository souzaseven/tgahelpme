<?php
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

/*
==============================================================
decisao_fila.php — FASE 7 COMPLETA
--------------------------------------------------------------
ROTAS:
- entrar_pausa      → primeiro da fila entra na pausa
- ir_segundo        → primeiro vai para posição 2
- ir_ultimo         → primeiro vai para última posição
- sair_espera       → operador abandona a fila
- solicitar_troca   → cria pedido de troca (não executa)
- responder_troca   → aceita/recusa troca
==============================================================
*/

$acao   = $_POST['acao']   ?? '';
$id     = intval($_POST['id'] ?? 0);
$equipe = trim($_POST['equipe'] ?? '');

if (!$acao || !$id || !$equipe) {
    resposta(false, "Parâmetros inválidos.");
}

try {

    // Buscar operador alvo
    $sql = $pdo->prepare("
        SELECT id, nome_usuario, status, posicao_fila
        FROM controle_pausa
        WHERE id = :id AND equipe = :e
        LIMIT 1
    ");
    $sql->execute([":id" => $id, ":e" => $equipe]);
    $operador = $sql->fetch();

    if (!$operador) {
        resposta(false, "Operador não encontrado.");
    }

    // Buscar fila completa da equipe
    $fila = obterFila($pdo, $equipe);
    $qtFila = count($fila);

    // Buscar quantos estão em pausa
    $qtdPausa = obterQtdPausa($pdo, $equipe);

    switch ($acao) {

    /* ============================================================
       1 — ENTRAR EM PAUSA AGORA (primeiro da fila)
    ============================================================ */
    case "entrar_pausa":

        if ($operador["posicao_fila"] != 1) {
            resposta(false, "Somente o primeiro da fila pode entrar na pausa.");
        }

        if ($qtdPausa >= 2) {
            resposta(false, "Não há vagas para pausa no momento.");
        }

        // Remover da fila
        limparFilaID($pdo, $id);

        // Tornar em pausa
        $pdo->prepare("
            UPDATE controle_pausa
            SET status='pausa', inicio_pausa=NOW()
            WHERE id=:id
        ")->execute([":id"=>$id]);

        // Reorganizar fila
        reorganizarFila($pdo, $equipe);

        resposta(true, "Agora você está em PAUSA.");

    break;



    /* ============================================================
       2 — IR PARA SEGUNDO DA FILA (apenas o primeiro)
    ============================================================ */
    case "ir_segundo":

        if ($operador["posicao_fila"] != 1) {
            resposta(false, "Somente o primeiro pode ir para segundo.");
        }

        if ($qtFila < 2) {
            resposta(false, "Não existe segundo lugar (fila com 1 pessoa).");
        }

        $segundo = $fila[1]['id'];

        // Primeiro vira segundo
        atualizarPosicao($pdo, $id, 2);

        // Segundo vira primeiro
        atualizarPosicao($pdo, $segundo, 1);

        reorganizarFila($pdo, $equipe);

        resposta(true, "Você agora é o segundo da fila.");

    break;



    /* ============================================================
       3 — IR PARA ÚLTIMO DA FILA (somente primeiro)
    ============================================================ */
    case "ir_ultimo":

        if ($operador["posicao_fila"] != 1) {
            resposta(false, "Somente o primeiro pode ir para o último.");
        }

        $ultimaPos = $qtFila;

        // Primeiro vira último
        atualizarPosicao($pdo, $id, $ultimaPos);

        // Todos acima sobem −1
        for ($i = 2; $i <= $ultimaPos; $i++) {
            atualizarPosicao($pdo, $fila[$i-1]['id'], $i - 1);
        }

        reorganizarFila($pdo, $equipe);
        resposta(true, "Você foi para o último da fila.");

    break;



    /* ============================================================
       4 — SAIR DA ESPERA (qualquer um)
    ============================================================ */
    case "sair_espera":

        limparFilaID($pdo, $id);

        $pdo->prepare("
            UPDATE controle_pausa
            SET status='ativo',
                inicio_espera=NULL
            WHERE id=:id
        ")->execute([":id"=>$id]);

        reorganizarFila($pdo, $equipe);
        resposta(true, "Você saiu da fila e agora está disponível.");

    break;



    /* ============================================================
       5 — SOLICITAR TROCA (apenas cria requisição)
    ============================================================ */
    case "solicitar_troca":

        $idAlvo = intval($_POST['alvo'] ?? 0);
        if (!$idAlvo) resposta(false, "ID do operador alvo não informado.");

        $pdo->prepare("
            INSERT INTO controle_pausa_trocas
            (solicitante_id, alvo_id, equipe, data_pedido, status)
            VALUES (:s, :a, :e, NOW(), 'pendente')
        ")->execute([
            ":s"=>$id,
            ":a"=>$idAlvo,
            ":e"=>$equipe
        ]);

        resposta(true, "Pedido de troca enviado.");

    break;



    /* ============================================================
       6 — RESPONDER TROCA (aceitar ou recusar)
    ============================================================ */
    case "responder_troca":

        $trocaId = intval($_POST['troca_id'] ?? 0);
        $aceito  = ($_POST['aceito'] ?? "0") === "1";

        $sql = $pdo->prepare("
            SELECT * FROM controle_pausa_trocas
            WHERE id=:id AND equipe=:e AND status='pendente'
        ");
        $sql->execute([":id"=>$trocaId, ":e"=>$equipe]);
        $pedido = $sql->fetch();

        if (!$pedido) resposta(false, "Pedido não encontrado ou inválido.");

        if (!$aceito) {
            // Recusado
            $pdo->prepare("
                UPDATE controle_pausa_trocas
                SET status='recusado'
                WHERE id=:id
            ")->execute([":id"=>$trocaId]);

            resposta(true, "Você recusou a troca.");
        }

        // Aceitar a troca → troca posições
        trocarPosicoes($pdo, $pedido['solicitante_id'], $pedido['alvo_id'], $equipe);

        $pdo->prepare("
            UPDATE controle_pausa_trocas
            SET status='aceito'
            WHERE id=:id
        ")->execute([":id"=>$trocaId]);

        reorganizarFila($pdo, $equipe);

        resposta(true, "Troca realizada com sucesso.");

    break;



    default:
        resposta(false, "Ação desconhecida.");
    }

} catch (Exception $e) {
    resposta(false, $e->getMessage());
}



/* ============================================================
   FUNÇÕES AUXILIARES
============================================================ */

function resposta($ok, $msg){
    echo json_encode(["success"=>$ok, "mensagem"=>$msg]);
    exit;
}

function obterFila($pdo, $equipe){
    $s = $pdo->prepare("
        SELECT id, posicao_fila
        FROM controle_pausa
        WHERE equipe=:e AND status='espera'
        ORDER BY posicao_fila ASC
    ");
    $s->execute([":e"=>$equipe]);
    return $s->fetchAll();
}

function obterQtdPausa($pdo, $equipe){
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM controle_pausa
        WHERE equipe=:e AND status='pausa'
    ");
    $s->execute([":e"=>$equipe]);
    return intval($s->fetchColumn());
}

function atualizarPosicao($pdo, $id, $pos){
    $pdo->prepare("
        UPDATE controle_pausa
        SET posicao_fila=:p
        WHERE id=:id
    ")->execute([":p"=>$pos, ":id"=>$id]);
}

function limparFilaID($pdo, $id){
    $pdo->prepare("
        UPDATE controle_pausa
        SET posicao_fila=NULL,
            inicio_espera=NULL
        WHERE id=:id
    ")->execute([":id"=>$id]);
}

function reorganizarFila($pdo, $equipe){
    $fila = obterFila($pdo, $equipe);
    $pos = 1;
    foreach ($fila as $f) {
        atualizarPosicao($pdo, $f['id'], $pos);
        $pos++;
    }
}

function trocarPosicoes($pdo, $idA, $idB, $equipe){
    $s = $pdo->prepare("
        SELECT id, posicao_fila FROM controle_pausa
        WHERE id IN (:a,:b)
    ");
    $s->execute([":a"=>$idA,":b"=>$idB]);
    $rows = $s->fetchAll();

    if (count($rows) != 2) return;

    $pA = $rows[0]['posicao_fila'];
    $pB = $rows[1]['posicao_fila'];

    atualizarPosicao($pdo, $idA, $pB);
    atualizarPosicao($pdo, $idB, $pA);
}
