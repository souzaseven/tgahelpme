<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';
require_once __DIR__ . '/../includes/config.php';

function tabelaHabilidadesDisponivel(PDO $pdo): bool
{
    try {
        $pdo->query("SELECT 1 FROM repasse_operador_habilidades LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$usaTabelaHabilidades = tabelaHabilidadesDisponivel($pdo);

$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['semana'], $body['operadores'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Payload inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$semana = $body['semana'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Semana inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$operadores  = $body['operadores'];
$criadoPor   = (int) $_SESSION['usuario_id'];
$idsEnviados = [];

$idsConsulta = [];
foreach ($operadores as $op) {
    $id = (int) ($op['operador_id'] ?? 0);
    if ($id > 0) {
        $idsConsulta[$id] = $id;
    }
}

$snapshotOperadores = [];
if (!empty($idsConsulta)) {
    $placeholders = implode(',', array_fill(0, count($idsConsulta), '?'));
    $habilidadesMap = [];

    if ($usaTabelaHabilidades) {
        $stmtHab = $pdo->prepare(
            "SELECT operador_id, eh_tef
             FROM repasse_operador_habilidades
             WHERE operador_id IN ($placeholders)"
        );
        $stmtHab->execute(array_values($idsConsulta));
        foreach ($stmtHab->fetchAll() as $hab) {
            $habilidadesMap[(int) $hab['operador_id']] = (int) $hab['eh_tef'] === 1;
        }
    }

    $stmtOperadores = $pdo->prepare(
        "SELECT evolux_agent_id, nome, lider, fila, evolux_queue_id
         FROM operadores
         WHERE evolux_agent_id IN ($placeholders)"
    );
    $stmtOperadores->execute(array_values($idsConsulta));

    foreach ($stmtOperadores->fetchAll() as $row) {
        $id = (int) $row['evolux_agent_id'];
        $snapshotOperadores[$id] = [
            'nome'            => $row['nome'],
            'lider'           => $row['lider'],
            'fila'            => $row['fila'],
            'evolux_queue_id' => $row['evolux_queue_id'] !== null ? (int) $row['evolux_queue_id'] : null,
            'eh_tef'          => ($habilidadesMap[$id] ?? in_array($id, TEF_IDS, true)) ? 1 : 0,
        ];
    }
}

$upsert = $pdo->prepare("
    INSERT INTO repasse_tickets
        (operador_id, nome_operador, lider, fila, evolux_queue_id, eh_tef, semana, seg, ter, qua, qui, sex, sab, em_ferias, nao_repassar, criado_por)
    VALUES
        (:operador_id, :nome, :lider, :fila, :evolux_queue_id, :eh_tef, :semana, :seg, :ter, :qua, :qui, :sex, :sab, :em_ferias, :nao_repassar, :criado_por)
    ON DUPLICATE KEY UPDATE
        nome_operador = VALUES(nome_operador),
        lider         = VALUES(lider),
        fila          = VALUES(fila),
        evolux_queue_id = VALUES(evolux_queue_id),
        eh_tef        = VALUES(eh_tef),
        seg           = VALUES(seg),
        ter           = VALUES(ter),
        qua           = VALUES(qua),
        qui           = VALUES(qui),
        sex           = VALUES(sex),
        sab           = VALUES(sab),
        em_ferias     = VALUES(em_ferias),
        nao_repassar  = VALUES(nao_repassar),
        criado_por    = VALUES(criado_por)
");

try { $pdo->query("SELECT 1 FROM repasse_tickets LIMIT 1"); }
catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Tabela repasse_tickets não existe. Execute setup.sql primeiro.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Férias ativas — em_ferias=1 apenas se a semana INTEIRA estiver coberta
$feriasAtivasMap = [];
try {
    $sabadoF   = date('Y-m-d', strtotime($semana . ' +5 days'));
    $semanaTs  = strtotime($semana . ' 12:00:00');
    $stmtF = $pdo->prepare(
        "SELECT operador_id, data_inicio, data_fim
         FROM operador_ferias
         WHERE data_inicio <= :sabado AND data_fim >= :semana"
    );
    $stmtF->execute([':sabado' => $sabadoF, ':semana' => $semana]);
    foreach ($stmtF->fetchAll() as $f) {
        $vacStart = strtotime($f['data_inicio'] . ' 12:00:00');
        $vacEnd   = strtotime($f['data_fim']    . ' 12:00:00');
        $count = 0;
        for ($i = 0; $i < 6; $i++) {
            $diaTs = $semanaTs + ($i * 86400);
            if ($diaTs >= $vacStart && $diaTs <= $vacEnd) $count++;
        }
        $feriasAtivasMap[(int) $f['operador_id']] = ($count === 6);
    }
} catch (PDOException $e) {
    // Tabela ainda não existe
}

// Snapshot dos valores atuais — necessário para detectar mudanças no histórico
$valoresAnteriores = [];
if (!empty($idsConsulta)) {
    $phAnt   = implode(',', array_fill(0, count($idsConsulta), '?'));
    $stmtAnt = $pdo->prepare(
        "SELECT operador_id, seg, ter, qua, qui, sex, sab
         FROM repasse_tickets WHERE semana = ? AND operador_id IN ($phAnt)"
    );
    $stmtAnt->execute(array_merge([$semana], array_values($idsConsulta)));
    foreach ($stmtAnt->fetchAll() as $row) {
        $valoresAnteriores[(int) $row['operador_id']] = $row;
    }
}

try {
    $pdo->beginTransaction();

    foreach ($operadores as $op) {
        $id = (int) ($op['operador_id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $idsEnviados[] = $id;

        $snapshot = $snapshotOperadores[$id] ?? [
            'nome'            => trim($op['nome'] ?? ''),
            'lider'           => null,
            'fila'            => null,
            'evolux_queue_id' => null,
            'eh_tef'          => in_array($id, TEF_IDS, true) ? 1 : 0,
        ];

        $upsert->execute([
            ':operador_id'     => $id,
            ':nome'            => substr($snapshot['nome'], 0, 150),
            ':lider'           => $snapshot['lider'] !== null ? substr($snapshot['lider'], 0, 150) : null,
            ':fila'            => $snapshot['fila'] !== null ? substr($snapshot['fila'], 0, 150) : null,
            ':evolux_queue_id' => $snapshot['evolux_queue_id'],
            ':eh_tef'          => (int) $snapshot['eh_tef'],
            ':semana'          => $semana,
            ':seg'             => max(0, intval($op['seg'] ?? 0)),
            ':ter'             => max(0, intval($op['ter'] ?? 0)),
            ':qua'             => max(0, intval($op['qua'] ?? 0)),
            ':qui'             => max(0, intval($op['qui'] ?? 0)),
            ':sex'             => max(0, intval($op['sex'] ?? 0)),
            ':sab'             => max(0, intval($op['sab'] ?? 0)),
            ':em_ferias'       => ($feriasAtivasMap[$id] ?? false) ? 1 : 0,
            ':nao_repassar'    => !empty($op['nao_repassar']) ? 1 : 0,
            ':criado_por'      => $criadoPor,
        ]);
    }

    if (!empty($idsEnviados)) {
        $placeholders = implode(',', array_fill(0, count($idsEnviados), '?'));
        $params       = array_merge([$semana], $idsEnviados);
        $pdo->prepare(
            "DELETE FROM repasse_tickets
             WHERE semana = ? AND operador_id NOT IN ($placeholders)"
        )->execute($params);
    } else {
        $pdo->prepare("DELETE FROM repasse_tickets WHERE semana = ?")->execute([$semana]);
    }

    $pdo->commit();

    // Grava historico para os operadores cujos valores mudaram
    try {
        $pdo->query("SELECT 1 FROM repasse_historico LIMIT 1");
        $stmtHist = $pdo->prepare("
            INSERT INTO repasse_historico
                (operador_id, nome_operador, lider, semana,
                 seg, ter, qua, qui, sex, sab, total_novo, total_anterior,
                 alterado_por, nome_alterador)
            VALUES
                (:operador_id, :nome_operador, :lider, :semana,
                 :seg, :ter, :qua, :qui, :sex, :sab, :total_novo, :total_anterior,
                 :alterado_por, :nome_alterador)
        ");
        foreach ($operadores as $op) {
            $id = (int) ($op['operador_id'] ?? 0);
            if ($id <= 0) continue;

            $segN = max(0, intval($op['seg'] ?? 0));
            $terN = max(0, intval($op['ter'] ?? 0));
            $quaN = max(0, intval($op['qua'] ?? 0));
            $quiN = max(0, intval($op['qui'] ?? 0));
            $sexN = max(0, intval($op['sex'] ?? 0));
            $sabN = max(0, intval($op['sab'] ?? 0));
            $totalNovo = $segN + $terN + $quaN + $quiN + $sexN + $sabN;

            $ant           = $valoresAnteriores[$id] ?? null;
            $totalAnterior = $ant
                ? (intval($ant['seg']) + intval($ant['ter']) + intval($ant['qua']) +
                   intval($ant['qui']) + intval($ant['sex']) + intval($ant['sab']))
                : 0;

            $mudou = ($ant === null)
                || intval($ant['seg']) !== $segN || intval($ant['ter']) !== $terN
                || intval($ant['qua']) !== $quaN  || intval($ant['qui']) !== $quiN
                || intval($ant['sex']) !== $sexN  || intval($ant['sab']) !== $sabN;

            if (!$mudou) continue;

            $snap = $snapshotOperadores[$id] ?? null;
            $stmtHist->execute([
                ':operador_id'    => $id,
                ':nome_operador'  => $snap['nome'] ?? substr(trim($op['nome'] ?? ''), 0, 150),
                ':lider'          => $snap['lider'] ?? null,
                ':semana'         => $semana,
                ':seg'            => $segN,
                ':ter'            => $terN,
                ':qua'            => $quaN,
                ':qui'            => $quiN,
                ':sex'            => $sexN,
                ':sab'            => $sabN,
                ':total_novo'     => $totalNovo,
                ':total_anterior' => $totalAnterior,
                ':alterado_por'   => $criadoPor,
                ':nome_alterador' => $_SESSION['nome'],
            ]);
        }
    } catch (PDOException $eHist) {
        // Tabela ainda nao existe — silencioso ate rodar setup.php
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao salvar os dados do repasse.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'  => true,
    'mensagem' => count($idsEnviados) . ' registro(s) salvos com sucesso.',
], JSON_UNESCAPED_UNICODE);
