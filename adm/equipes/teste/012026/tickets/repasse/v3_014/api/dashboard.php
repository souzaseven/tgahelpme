<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Nao autenticado.'], JSON_UNESCAPED_UNICODE);
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

$de    = $_GET['de']    ?? '';
$ate   = $_GET['ate']   ?? '';
$lider = $_GET['lider'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Parametros de data invalidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $params = [':de' => $de, ':ate' => $ate];

    $whereLider = '';
    if ($lider !== '') {
        $whereLider  = " AND COALESCE(NULLIF(rt.lider, ''), o.lider) = :lider";
        $params[':lider'] = $lider;
    }

    // ── Dados por semana + operador ──────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT
            rt.semana,
            rt.operador_id,
            COALESCE(NULLIF(rt.nome_operador, ''), o.nome) AS operador_nome,
            COALESCE(NULLIF(rt.lider, ''), o.lider) AS lider,
            COALESCE(NULLIF(rt.fila, ''), o.fila) AS fila,
            o.data_contratacao,
            rt.seg, rt.ter, rt.qua, rt.qui, rt.sex, rt.sab,
            (rt.seg + rt.ter + rt.qua + rt.qui + rt.sex + rt.sab) AS total_semana,
            rt.em_ferias
         FROM repasse_tickets rt
         LEFT JOIN operadores o ON o.evolux_agent_id = rt.operador_id
         WHERE rt.semana BETWEEN :de AND :ate
         {$whereLider}
         ORDER BY rt.semana,
                  COALESCE(NULLIF(rt.lider, ''), o.lider),
                  COALESCE(NULLIF(rt.nome_operador, ''), o.nome)"
    );
    $stmt->execute($params);
    $linhas = $stmt->fetchAll();

    // ── Resumo geral ─────────────────────────────────────────────────────────
    $totalTickets   = 0;
    $totalFerias    = 0;
    $semanas        = [];
    $porLider       = [];
    $porOperador    = [];

    $habilidadesMap = [];
    $idsOperadores = array_values(array_unique(array_map(fn($r) => (int) $r['operador_id'], $linhas)));
    if ($usaTabelaHabilidades && !empty($idsOperadores)) {
        $ph = implode(',', array_fill(0, count($idsOperadores), '?'));
        $stmtHab = $pdo->prepare(
            "SELECT operador_id, eh_tef, troca_regime
             FROM repasse_operador_habilidades
             WHERE operador_id IN ($ph)"
        );
        $stmtHab->execute($idsOperadores);

        foreach ($stmtHab->fetchAll() as $hab) {
            $habilidadesMap[(int) $hab['operador_id']] = [
                'tef' => (int) $hab['eh_tef'] === 1,
                'troca_regime' => (int) $hab['troca_regime'] === 1,
            ];
        }
    }

    foreach ($linhas as $r) {
        $totalTickets += (int) $r['total_semana'];
        if ($r['em_ferias']) $totalFerias++;

        $semanas[$r['semana']] = true;

        $ldr = $r['lider'] ?? 'Sem equipe';
        if (!isset($porLider[$ldr])) $porLider[$ldr] = ['lider' => $ldr, 'total' => 0, 'ferias' => 0];
        $porLider[$ldr]['total']  += (int) $r['total_semana'];
        if ($r['em_ferias']) $porLider[$ldr]['ferias']++;

        $oid = (int) $r['operador_id'];
        $skills = $habilidadesMap[$oid] ?? [
            'tef' => in_array($oid, TEF_IDS, true),
            'troca_regime' => in_array($oid, TROCA_REGIME_IDS, true),
        ];
        if (!isset($porOperador[$oid])) {
            $porOperador[$oid] = [
                'id'    => $oid,
                'nome'  => $r['operador_nome'],
                'lider' => $r['lider'],
                'fila'  => $r['fila'],
                'tef'   => $skills['tef'],
                'troca_regime' => $skills['troca_regime'],
                'data_contratacao' => $r['data_contratacao'],
                'tempo_empresa_meses' => calcularTempoEmpresaMeses($r['data_contratacao']),
                'tempo_empresa_texto' => formatarTempoEmpresa($r['data_contratacao']),
                'total' => 0,
                'semanas_ativas' => 0,
            ];
        }
        $porOperador[$oid]['total'] += (int) $r['total_semana'];
        if (!$r['em_ferias'] && (int) $r['total_semana'] > 0) $porOperador[$oid]['semanas_ativas']++;
    }

    // Ordena operadores por total desc
    usort($porOperador, fn($a, $b) => $b['total'] <=> $a['total']);

    // Ordena líderes por ordem definida
    $lideresDef = array_keys(ORDEM_EQUIPES);
    usort($porLider, function ($a, $b) use ($lideresDef) {
        $pa = array_search($a['lider'], $lideresDef);
        $pb = array_search($b['lider'], $lideresDef);
        $pa = $pa === false ? PHP_INT_MAX : $pa;
        $pb = $pb === false ? PHP_INT_MAX : $pb;
        return $pa <=> $pb;
    });

    echo json_encode([
        'resumo' => [
            'total_tickets'   => $totalTickets,
            'total_semanas'   => count($semanas),
            'total_registros' => count($linhas),
            'total_ferias'    => $totalFerias,
        ],
        'por_lider'    => array_values($porLider),
        'por_operador' => array_values($porOperador),
        'linhas'       => $linhas,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao consultar dados.'], JSON_UNESCAPED_UNICODE);
}
