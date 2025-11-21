<?php
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

$acao   = $_POST['acao']   ?? '';
$id     = intval($_POST['id'] ?? 0);
$equipe = $_POST['equipe'] ?? '';
$alvo   = intval($_POST['alvo'] ?? 0); // usado em troca

if (!$acao || !$id || !$equipe) {
    echo json_encode([
        "success" => false,
        "erro"    => "Parâmetros inválidos",
        "debug"   => $_POST
    ]);
    exit;
}

try {

    /* ============================================================
       FUNÇÃO AUXILIAR — reorganizar toda fila
    ============================================================ */
    function reorganizarFila($pdo, $equipe) {
        $sql = $pdo->prepare("
            SELECT id 
            FROM controle_pausa
            WHERE equipe = :equipe AND status = 'espera'
            ORDER BY posicao_fila ASC
        ");
        $sql->execute([":equipe" => $equipe]);
        $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

        $pos = 1;
        foreach ($fila as $f) {
            $upd = $pdo->prepare("
                UPDATE controle_pausa
                SET posicao_fila = :p
                WHERE id = :id
            ");
            $upd->execute([
                ":p" => $pos++,
                ":id" => $f["id"]
            ]);
        }
    }

    /* ============================================================
       AÇÃO: SAIR DA ESPERA
       - Remove da fila
       - Volta status para ativo
       - Reorganiza fila inteira
    ============================================================ */
    if ($acao === "sair_espera") {

        $sql = $pdo->prepare("
            UPDATE controle_pausa
            SET status='ativo', posicao_fila=NULL, inicio_espera=NULL
            WHERE id = :id AND equipe = :equipe
        ");
        $sql->execute([":id"=>$id, ":equipe"=>$equipe]);

        reorganizarFila($pdo, $equipe);

        echo json_encode([
            "success"  => true,
            "acao"     => "sair_espera",
            "mensagem" => "Operador saiu da fila"
        ]);
        exit;
    }

    /* ============================================================
       AÇÃO: ENTRAR EM PAUSA (primeiro da fila)
       - Verifica se há vaga
       - Troca status para pausa
       - Remove da fila
       - Reorganiza fila
    ============================================================ */
    if ($acao === "entrar_pausa") {

        // verificar quantidade em pausa
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM controle_pausa
            WHERE equipe = :e AND status='pausa'
        ");
        $q->execute([":e"=>$equipe]);
        $qtd = intval($q->fetchColumn());

        if ($qtd >= 2) {
            echo json_encode([
                "success"=>false,
                "erro"=>"Não há vagas para pausa"
            ]);
            exit;
        }

        // mover para pausa
        $sql = $pdo->prepare("
            UPDATE controle_pausa
            SET status='pausa',
                inicio_pausa = NOW(),
                posicao_fila=NULL,
                inicio_espera=NULL
            WHERE id = :id
        ");
        $sql->execute([":id"=>$id]);

        reorganizarFila($pdo, $equipe);

        echo json_encode([
            "success"=>true,
            "acao"=>"entrar_pausa",
            "mensagem"=>"Operador entrou em pausa"
        ]);
        exit;
    }

    /* ============================================================
       AÇÃO: IR PARA SEGUNDO (só se for o primeiro)
    ============================================================ */
    if ($acao === "ir_segundo") {

        // pegar fila atual
        $sql = $pdo->prepare("
            SELECT id, posicao_fila FROM controle_pausa
            WHERE equipe=:e AND status='espera'
            ORDER BY posicao_fila ASC
        ");
        $sql->execute([":e"=>$equipe]);
        $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (count($fila) < 2) {
            echo json_encode(["success"=>false,"erro"=>"Não há como ir para segundo"]);
            exit;
        }

        // reorganizar manualmente
        $novo = [];
        $novo[] = $fila[1]; // segundo vira primeiro
        $novo[] = $fila[0]; // primeiro vira segundo
        for ($i=2;$i<count($fila);$i++) $novo[]=$fila[$i];

        // aplicar nova ordem
        $pos = 1;
        foreach ($novo as $f) {
            $upd = $pdo->prepare("
                UPDATE controle_pausa SET posicao_fila=:p WHERE id=:id
            ");
            $upd->execute([":p"=>$pos++,":id"=>$f["id"]]);
        }

        echo json_encode([
            "success"=>true,
            "acao"=>"ir_segundo",
            "mensagem"=>"Agora você está em 2º lugar"
        ]);
        exit;
    }

    /* ============================================================
       AÇÃO: IR PARA ÚLTIMO (só se for o primeiro)
    ============================================================ */
    if ($acao === "ir_ultimo") {

        $sql = $pdo->prepare("
            SELECT id, posicao_fila FROM controle_pausa
            WHERE equipe=:e AND status='espera'
            ORDER BY posicao_fila ASC
        ");
        $sql->execute([":e"=>$equipe]);
        $fila = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (count($fila) < 2) {
            echo json_encode(["success"=>false,"erro"=>"Não há como ir para último"]);
            exit;
        }

        $primeiro = array_shift($fila); // remove o primeiro
        $fila[] = $primeiro;            // adiciona no final

        $pos = 1;
        foreach ($fila as $f) {
            $upd = $pdo->prepare("
                UPDATE controle_pausa SET posicao_fila=:p WHERE id=:id
            ");
            $upd->execute([":p"=>$pos++,":id"=>$f["id"]]);
        }

        echo json_encode([
            "success"=>true,
            "acao"=>"ir_ultimo",
            "mensagem"=>"Agora você é o último da fila"
        ]);
        exit;
    }

    /* ============================================================
       AÇÃO: SOLICITAR TROCA
       - grava pedido de troca
    ============================================================ */
    if ($acao === "solicitar_troca") {

        if (!$alvo) {
            echo json_encode(["success"=>false,"erro"=>"Operador alvo inválido"]);
            exit;
        }

        // registrar pedido
        $sql = $pdo->prepare("
            UPDATE controle_pausa
            SET troca_solicitada = :alvo
            WHERE id = :id
        ");
        $sql->execute([
            ":alvo" => $alvo,
            ":id"   => $id
        ]);

        echo json_encode([
            "success"=>true,
            "acao"=>"solicitar_troca",
            "mensagem"=>"Solicitação enviada"
        ]);
        exit;
    }

    /* ============================================================
       AÇÃO: RESPONDER TROCA
       - aceitar → troca posições
       - recusar → limpa pedido
    ============================================================ */
    if ($acao === "responder_troca") {

        $aceitou = intval($_POST["aceitou"] ?? 0);

        if (!$alvo) {
            echo json_encode(["success"=>false,"erro"=>"Operador alvo inválido"]);
            exit;
        }

        if ($aceitou == 0) {
            // apenas limpar pedido
            $clean = $pdo->prepare("
                UPDATE controle_pausa
                SET troca_solicitada = NULL
                WHERE id = :alvo
            ");
            $clean->execute([":alvo"=>$alvo]);

            echo json_encode([
                "success"=>true,
                "acao"=>"responder_troca",
                "mensagem"=>"Troca recusada"
            ]);
            exit;
        }

        // aceitar troca → inverter posições
        $sql = $pdo->prepare("
            SELECT id,posicao_fila FROM controle_pausa
            WHERE id IN (:a,:b)
        ");
        $sql->execute([":a"=>$alvo,":b"=>$id]);
        $dados = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (count($dados) != 2) {
            echo json_encode(["success"=>false,"erro"=>"Erro ao localizar operadores"]);
            exit;
        }

        $p1 = $dados[0];
        $p2 = $dados[1];

        // troca efetiva
        $upd1 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila=:p WHERE id=:id");
        $upd1->execute([":p"=>$p2["posicao_fila"],":id"=>$p1["id"]]);

        $upd2 = $pdo->prepare("UPDATE controle_pausa SET posicao_fila=:p WHERE id=:id");
        $upd2->execute([":p"=>$p1["posicao_fila"],":id"=>$p2["id"]]);

        // limpar pedido
        $clean = $pdo->prepare("
            UPDATE controle_pausa
            SET troca_solicitada = NULL
            WHERE id=:a OR id=:b
        ");
        $clean->execute([":a"=>$alvo,":b"=>$id]);

        reorganizarFila($pdo, $equipe);

        echo json_encode([
            "success"=>true,
            "acao"=>"responder_troca",
            "mensagem"=>"Troca realizada"
        ]);
        exit;
    }

    /* ============================================================
       DEFAULT
    ============================================================ */
    echo json_encode([
        "success"=>false,
        "erro"=>"Ação desconhecida"
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "success"=>false,
        "erro"=>$e->getMessage()
    ]);
}
