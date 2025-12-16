<?php
require_once "conexao.php";

function resposta($arr){
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    // =======================================================
    // OPERADORES EM PAUSA (TEMPO ATUAL)
    // =======================================================
    $pausa = $pdo->query("
        SELECT 
            nome_usuario AS nome,
            equipe,
            TIMESTAMPDIFF(SECOND, inicio_pausa, NOW()) AS tempo
        FROM controle_pausa
        WHERE status = 'pausa'
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Operadores que excederam 20 min
    $expirados = array_filter($pausa, fn($x) => $x['tempo'] >= 1200);



    // =======================================================
    // RANKING DE PAUSAS — HOJE
    // OBS: Usa nome_usuario no GROUP BY para evitar conflitos
    // =======================================================
    $ranking_pausa = $pdo->query("
        SELECT 
            nome_usuario AS nome,
            equipe,
            SUM(tempo_total_pausa) AS total
        FROM controle_pausa
        GROUP BY nome_usuario, equipe
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);



    // =======================================================
    // RANKING DE FILA — HOJE
    // =======================================================
    $ranking_fila = $pdo->query("
        SELECT 
            nome_usuario AS nome,
            equipe,
            SUM(tempo_total_espera) AS total
        FROM controle_pausa
        GROUP BY nome_usuario, equipe
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);



    // =======================================================
    // EQUIPES COM MAIOR FILA AGORA
    // =======================================================
    $filas = $pdo->query("
        SELECT 
            equipe,
            COUNT(*) AS qtd
        FROM controle_pausa
        WHERE status = 'espera'
        GROUP BY equipe
        ORDER BY qtd DESC
    ")->fetchAll(PDO::FETCH_ASSOC);



    // =======================================================
    // 🔥 RETORNO FINAL — TODOS OS DADOS ORGANIZADOS
    // =======================================================
    resposta([
        "success"        => true,

        // Operadores em pausa
        "pausa"          => $pausa,

        // Operadores que passaram de 20 minutos
        "expirados"      => array_values($expirados),

        // Ranking de pausas acumuladas (hoje)
        "ranking_pausa"  => $ranking_pausa,

        // Ranking fila acumulada (hoje)
        "ranking_fila"   => $ranking_fila,

        // Quantidade de pessoas por equipe na fila atual
        "filas"          => $filas
    ]);

} catch (Exception $e) {

    resposta([
        "success" => false,
        "erro"    => $e->getMessage()
    ]);
}
