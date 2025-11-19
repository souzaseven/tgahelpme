<?php
// ============================================================
// status.php - Snapshot geral para o painel de pausas (v1.0)
// ============================================================
// Retorna:
//  - pausas ativas
//  - fila de espera
//  - operadores por equipe com status
//  - totais agregados
// ============================================================

require_once "conexao.php";

try {

    // ========================================================
    // 🔧 1) AJUSTE AQUI PARA O SEU BANCO DE DADOS
    // --------------------------------------------------------
    // Assumindo as seguintes tabelas/campos (exemplo):
    //
    //  Tabela: equipes
    //   - id
    //   - nome
    //
    //  Tabela: operadores
    //   - id
    //   - nome
    //   - equipe_id
    //   - ativo (0/1)
    //
    //  Tabela: controle_pausas
    //   - id
    //   - operador_id
    //   - equipe_id
    //   - status        (ativo, pausa, espera, aguardando, expirada)
    //   - motivo        (texto opcional)
    //   - inicio_status (datetime)
    //   - posicao_fila  (int, se estiver em fila)
    //   - atualizado_em (datetime)
    //
    // Se seus nomes forem diferentes, troque no SELECT abaixo.
    // ========================================================

    $sql = "
        SELECT
            e.id            AS equipe_id,
            e.nome          AS equipe_nome,
            o.id            AS operador_id,
            o.nome          AS operador_nome,
            COALESCE(cp.status, 'ativo') AS status,
            cp.motivo       AS motivo,
            cp.inicio_status AS inicio_status,
            cp.posicao_fila AS posicao_fila
        FROM operadores o
        INNER JOIN equipes e
            ON e.id = o.equipe_id
        LEFT JOIN controle_pausas cp
            ON cp.operador_id = o.id
           AND cp.ativo = 1      -- se você tiver um campo de controle de registro atual
        WHERE
            (o.ativo = 1 OR o.ativo IS NULL)
        ORDER BY
            e.nome ASC,
            o.nome ASC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    // Se nada encontrado
    if (!$rows) {
        respostaJSON([
            "success" => true,
            "atualizado_em" => date('c'),
            "pausas" => [],
            "fila" => [],
            "equipes" => [],
            "totais" => [
                "total_operadores" => 0,
                "em_pausa" => 0,
                "em_espera" => 0,
                "ativos" => 0,
                "expiradas" => 0
            ]
        ]);
    }

    // ========================================================
    // 🔄 2) ORGANIZAR DADOS EM ESTRUTURAS
    // ========================================================
    $equipes = [];
    $pausas = [];
    $fila = [];
    $totais = [
        "total_operadores" => 0,
        "em_pausa"         => 0,
        "em_espera"        => 0,
        "ativos"           => 0,
        "expiradas"        => 0
    ];

    foreach ($rows as $row) {

        $equipeId   = (int) $row["equipe_id"];
        $equipeNome = $row["equipe_nome"];
        $opId       = (int) $row["operador_id"];
        $opNome     = $row["operador_nome"];
        $status     = strtolower($row["status"] ?? "ativo");
        $motivo     = $row["motivo"] ?? null;
        $inicio     = $row["inicio_status"] ?? null;
        $posFila    = $row["posicao_fila"] ?? null;

        // Garante entrada da equipe no array
        if (!isset($equipes[$equipeId])) {
            $equipes[$equipeId] = [
                "id"          => $equipeId,
                "nome"        => $equipeNome,
                "totais"      => [
                    "total_operadores" => 0,
                    "em_pausa"         => 0,
                    "em_espera"        => 0,
                    "ativos"           => 0,
                    "expiradas"        => 0
                ],
                "operadores"  => []
            ];
        }

        // Monta operador
        $operador = [
            "id"           => $opId,
            "nome"         => $opNome,
            "status"       => $status,
            "motivo"       => $motivo,
            "inicio_status"=> $inicio,
            "posicao_fila" => $posFila
        ];

        // Adiciona operador na equipe
        $equipes[$equipeId]["operadores"][] = $operador;

        // Incrementa totais globais e da equipe
        $totais["total_operadores"]++;
        $equipes[$equipeId]["totais"]["total_operadores"]++;

        switch ($status) {
            case "pausa":
                $totais["em_pausa"]++;
                $equipes[$equipeId]["totais"]["em_pausa"]++;
                $pausas[] = $operador;
                break;

            case "espera":
            case "aguardando":
                $totais["em_espera"]++;
                $equipes[$equipeId]["totais"]["em_espera"]++;
                $fila[] = $operador;
                break;

            case "expirada":
                $totais["expiradas"]++;
                $equipes[$equipeId]["totais"]["expiradas"]++;
                break;

            default: // ativo ou outros
                $totais["ativos"]++;
                $equipes[$equipeId]["totais"]["ativos"]++;
                break;
        }
    }

    // Ordenar fila pela posição, se existir
    usort($fila, function($a, $b){
        return (int)($a["posicao_fila"] ?? 0) <=> (int)($b["posicao_fila"] ?? 0);
    });

    // Reorganizar equipes em array indexado
    $equipesArray = array_values($equipes);

    // ========================================================
    // 🚀 3) RESPOSTA FINAL
    // ========================================================
    respostaJSON([
        "success"       => true,
        "atualizado_em" => date('c'),
        "pausas"        => $pausas,
        "fila"          => $fila,
        "equipes"       => $equipesArray,
        "totais"        => $totais
    ]);

} catch (Exception $e) {

    respostaJSON([
        "success" => false,
        "erro"    => "Erro ao montar status do painel.",
        "detalhe" => $e->getMessage()
    ]);

}
