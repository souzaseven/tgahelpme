<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';

$semana = $_GET['semana'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parâmetro semana inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$resultado = [];
$resumoMesOperadores = [];

try {
    $stmt = $pdo->prepare(
        "SELECT operador_id, seg, ter, qua, qui, sex, sab, em_ferias, nao_repassar, nao_repassar_herdado
         FROM repasse_tickets
         WHERE semana = :semana"
    );
    $stmt->execute([':semana' => $semana]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $resultado[(int) $row['operador_id']] = [
            'seg'                  => (int) $row['seg'],
            'ter'                  => (int) $row['ter'],
            'qua'                  => (int) $row['qua'],
            'qui'                  => (int) $row['qui'],
            'sex'                  => (int) $row['sex'],
            'sab'                  => (int) $row['sab'],
            'em_ferias'            => (bool) $row['em_ferias'],
            'nao_repassar'         => (bool) $row['nao_repassar'],
            'nao_repassar_herdado' => (bool) $row['nao_repassar_herdado'],
        ];
    }

    $inicioMes = date('Y-m-01', strtotime($semana . ' 12:00:00'));
    $fimMes = date('Y-m-t', strtotime($semana . ' 12:00:00'));
    $inicioBuscaMes = date('Y-m-d', strtotime($inicioMes . ' -6 days'));
    $anoMes = substr($inicioMes, 0, 7);

    $stmtMes = $pdo->prepare(
        "SELECT operador_id, semana, seg, ter, qua, qui, sex, sab
         FROM repasse_tickets
         WHERE semana BETWEEN :inicio_busca_mes AND :fim_mes"
    );
    $stmtMes->execute([
        ':inicio_busca_mes' => $inicioBuscaMes,
        ':fim_mes'          => $fimMes,
    ]);

    $dias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];

    foreach ($stmtMes->fetchAll() as $row) {
        $operadorId = (int) $row['operador_id'];

        if (!isset($resumoMesOperadores[$operadorId])) {
            $resumoMesOperadores[$operadorId] = 0;
        }

        foreach ($dias as $offset => $dia) {
            $dataDia = date('Y-m-d', strtotime($row['semana'] . " +{$offset} days"));
            if (substr($dataDia, 0, 7) !== $anoMes) {
                continue;
            }

            $resumoMesOperadores[$operadorId] += (int) $row[$dia];
        }
    }
} catch (PDOException $e) {
    // '42S02' = tabela não existe ainda (primeiro uso, antes do setup.php rodar
    // pela primeira vez) — nesse caso específico é seguro devolver vazio.
    // Qualquer OUTRO erro (ex.: coluna faltando após uma migração) tem que
    // aparecer alto e claro — nunca mais mascarar dados reais como zero.
    if ($e->getCode() !== '42S02') {
        http_response_code(500);
        echo json_encode([
            'erro' => 'Erro ao consultar os dados da semana no banco. Pode faltar rodar o setup.php após uma atualização. Detalhe: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Busca períodos de férias que se sobrepõem com qualquer dia da semana
$feriasAtivas = [];
try {
    $sabado = date('Y-m-d', strtotime($semana . ' +5 days'));
    $stmtFerias = $pdo->prepare(
        "SELECT id, operador_id, data_inicio, data_fim
         FROM operador_ferias
         WHERE data_inicio <= :sabado AND data_fim >= :semana"
    );
    $stmtFerias->execute([':sabado' => $sabado, ':semana' => $semana]);

    $diasNomes = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    $semanaTs  = strtotime($semana . ' 12:00:00');

    foreach ($stmtFerias->fetchAll() as $f) {
        $opId     = (int) $f['operador_id'];
        $vacStart = strtotime($f['data_inicio'] . ' 12:00:00');
        $vacEnd   = strtotime($f['data_fim']    . ' 12:00:00');

        $diasFerias = [];
        for ($i = 0; $i < 6; $i++) {
            $diaTs = $semanaTs + ($i * 86400);
            if ($diaTs >= $vacStart && $diaTs <= $vacEnd) {
                $diasFerias[] = $diasNomes[$i];
            }
        }

        $semanaInteira = count($diasFerias) === 6;

        $feriasAtivas[$opId] = [
            'id'          => (int) $f['id'],
            'data_inicio' => $f['data_inicio'],
            'data_fim'    => $f['data_fim'],
            'dias_ferias' => $diasFerias,
            'semana_toda' => $semanaInteira,
        ];

        // Só marca em_ferias=true no registro se a semana inteira for férias
        if ($semanaInteira && isset($resultado[$opId])) {
            $resultado[$opId]['em_ferias'] = true;
        }
    }
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') {
        http_response_code(500);
        echo json_encode([
            'erro' => 'Erro ao consultar férias no banco. Detalhe: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Tabela operador_ferias ainda não existe (primeiro uso) — ignora
}

echo json_encode([
    'semana'                => $semana,
    'registros'             => $resultado,
    'ferias_ativas'         => $feriasAtivas,
    'resumo_mes_operadores' => $resumoMesOperadores,
], JSON_UNESCAPED_UNICODE);
