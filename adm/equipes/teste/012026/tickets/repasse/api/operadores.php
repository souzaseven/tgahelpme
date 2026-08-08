<?php
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
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

function calcularTempoEmpresaMeses(?string $dataContratacao): ?int
{
    if (empty($dataContratacao)) {
        return null;
    }

    try {
        $contratacao = new DateTimeImmutable($dataContratacao);
        $hoje = new DateTimeImmutable('today');

        if ($contratacao > $hoje) {
            return 0;
        }

        $diff = $contratacao->diff($hoje);
        return ($diff->y * 12) + $diff->m;
    } catch (Exception $e) {
        return null;
    }
}

function formatarTempoEmpresa(?string $dataContratacao): string
{
    $meses = calcularTempoEmpresaMeses($dataContratacao);
    if ($meses === null) {
        return 'Sem data';
    }

    if ($meses === 0) {
        return 'Menos de 1 mes';
    }

    $anos = intdiv($meses, 12);
    $mesesRestantes = $meses % 12;
    $partes = [];

    if ($anos > 0) {
        $partes[] = $anos . ' ' . ($anos === 1 ? 'ano' : 'anos');
    }

    if ($mesesRestantes > 0) {
        $partes[] = $mesesRestantes . ' ' . ($mesesRestantes === 1 ? 'mes' : 'meses');
    }

    return implode(' e ', $partes);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'POST') {
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Apenas administradores podem alterar operadores.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $operadorId = (int) ($body['operador_id'] ?? 0);
    $dataContratacao = trim((string) ($body['data_contratacao'] ?? ''));
    $tef = !empty($body['tef']) ? 1 : 0;
    $trocaRegime = !empty($body['troca_regime']) ? 1 : 0;

    if ($operadorId <= 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Operador inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($dataContratacao !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataContratacao)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Data de contratação inválida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE operadores
             SET data_contratacao = :data_contratacao
             WHERE evolux_agent_id = :operador_id"
        );
        $stmt->execute([
            ':data_contratacao' => $dataContratacao !== '' ? $dataContratacao : null,
            ':operador_id'      => $operadorId,
        ]);

        if ($usaTabelaHabilidades) {
            $stmt = $pdo->prepare(
                "INSERT INTO repasse_operador_habilidades (operador_id, eh_tef, troca_regime)
                 VALUES (:operador_id, :eh_tef, :troca_regime)
                 ON DUPLICATE KEY UPDATE
                     eh_tef = VALUES(eh_tef),
                     troca_regime = VALUES(troca_regime)"
            );
            $stmt->execute([
                ':operador_id' => $operadorId,
                ':eh_tef' => $tef,
                ':troca_regime' => $trocaRegime,
            ]);
        }

        $queryOperador = $usaTabelaHabilidades
            ? "SELECT
                    o.evolux_agent_id,
                    o.nome,
                    o.data_contratacao,
                    h.eh_tef,
                    h.troca_regime
               FROM operadores o
               LEFT JOIN repasse_operador_habilidades h
                  ON h.operador_id = o.evolux_agent_id
               WHERE evolux_agent_id = :operador_id
               LIMIT 1"
            : "SELECT
                    evolux_agent_id,
                    nome,
                    data_contratacao,
                    NULL AS eh_tef,
                    NULL AS troca_regime
               FROM operadores
               WHERE evolux_agent_id = :operador_id
               LIMIT 1";
        $stmt = $pdo->prepare($queryOperador);
        $stmt->execute([':operador_id' => $operadorId]);
        $operador = $stmt->fetch();

        if (!$operador) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(404);
            echo json_encode(['erro' => 'Operador não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'operador' => [
                'id' => (int) $operador['evolux_agent_id'],
                'nome' => $operador['nome'],
                'data_contratacao' => $operador['data_contratacao'],
                'tef' => $operador['eh_tef'] !== null
                    ? ((int) $operador['eh_tef'] === 1)
                    : in_array($operadorId, TEF_IDS, true),
                'troca_regime' => $operador['troca_regime'] !== null
                    ? ((int) $operador['troca_regime'] === 1)
                    : in_array($operadorId, TROCA_REGIME_IDS, true),
                'tempo_empresa_meses' => calcularTempoEmpresaMeses($operador['data_contratacao']),
                'tempo_empresa_texto' => formatarTempoEmpresa($operador['data_contratacao']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao atualizar operador.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($metodo !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $queryOperadores = $usaTabelaHabilidades
        ? "SELECT
                o.evolux_agent_id,
                o.nome,
                o.lider,
                o.fila,
                o.evolux_queue_id,
                o.data_contratacao,
                h.eh_tef AS habilidade_tef,
                h.troca_regime AS habilidade_troca_regime
           FROM operadores o
           LEFT JOIN repasse_operador_habilidades h
              ON h.operador_id = o.evolux_agent_id
           WHERE lider IS NOT NULL AND lider != ''
             AND evolux_agent_id IS NOT NULL AND evolux_agent_id > 0
           ORDER BY lider, nome"
        : "SELECT
                evolux_agent_id,
                nome,
                lider,
                fila,
                evolux_queue_id,
                data_contratacao,
                NULL AS habilidade_tef,
                NULL AS habilidade_troca_regime
           FROM operadores
           WHERE lider IS NOT NULL AND lider != ''
             AND evolux_agent_id IS NOT NULL AND evolux_agent_id > 0
           ORDER BY lider, nome";
    $stmt = $pdo->query($queryOperadores);
    $todos = $stmt->fetchAll();
} catch (PDOException $e) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$ordemGlobal = array_merge(...array_values(ORDEM_EQUIPES));

$grupos = [];
foreach ($todos as $op) {
    $lider = $op['lider'];
    $id    = (int) $op['evolux_agent_id'];
    $tef = $op['habilidade_tef'] !== null
        ? ((int) $op['habilidade_tef'] === 1)
        : in_array($id, TEF_IDS, true);
    $trocaRegime = $op['habilidade_troca_regime'] !== null
        ? ((int) $op['habilidade_troca_regime'] === 1)
        : in_array($id, TROCA_REGIME_IDS, true);

    $grupos[$lider][] = [
        'id'    => $id,
        'nome'  => $op['nome'],
        'fila'  => $op['fila'],
        'fila_id' => (int) $op['evolux_queue_id'],
        'tef'   => $tef,
        'troca_regime' => $trocaRegime,
        'data_contratacao' => $op['data_contratacao'],
        'tempo_empresa_meses' => calcularTempoEmpresaMeses($op['data_contratacao']),
        'tempo_empresa_texto' => formatarTempoEmpresa($op['data_contratacao']),
    ];
}

// Ordena operadores dentro de cada equipe conforme ORDEM_EQUIPES
foreach ($grupos as $lider => &$lista) {
    $ordemLider = ORDEM_EQUIPES[$lider] ?? [];
    usort($lista, function ($a, $b) use ($ordemLider) {
        $posA = array_search($a['id'], $ordemLider);
        $posB = array_search($b['id'], $ordemLider);
        $posA = $posA === false ? PHP_INT_MAX : $posA;
        $posB = $posB === false ? PHP_INT_MAX : $posB;
        return $posA <=> $posB;
    });
}
unset($lista);

// Ordena as equipes conforme a ordem definida em ORDEM_EQUIPES
$lideresDef = array_keys(ORDEM_EQUIPES);
uksort($grupos, function ($a, $b) use ($lideresDef) {
    $pa = array_search($a, $lideresDef);
    $pb = array_search($b, $lideresDef);
    $pa = $pa === false ? PHP_INT_MAX : $pa;
    $pb = $pb === false ? PHP_INT_MAX : $pb;
    return $pa <=> $pb;
});

echo json_encode($grupos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
