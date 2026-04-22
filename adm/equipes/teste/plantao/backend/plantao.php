<?php
declare(strict_types=1);

/* =========================================================
   API Plantão (Sábado/Domingo)
   Autor: Anderson de Souza
   Status: FINAL / PRODUÇÃO
========================================================= */

/* =========================================================
   BLINDAGEM TOTAL
========================================================= */
ob_start();
date_default_timezone_set('America/Cuiaba');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   HANDLERS (evita 500 silencioso)
========================================================= */
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro interno na API',
        'detail'  => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================================================
   INÍCIO REAL DA API
========================================================= */
require_once __DIR__ . '/conexao.php';

/* =========================================================
   CSRF
========================================================= */
function csrf_ok(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/* =========================================================
   HORÁRIOS
========================================================= */
const HORARIO_SAB = '13:00 às 18:00';
const HORARIO_DOM = '07:30 às 11:30';

/* =========================================================
   DATAS (SÁBADO BASE)
========================================================= */
function sabadoDaSemana(DateTime $d): DateTime {
    $x = clone $d;
    $w = (int)$x->format('w');

    if ($w === 6) return $x;
    if ($w === 0) return $x->modify('-1 day');

    return $x->modify('+' . (6 - $w) . ' days');
}

function ultimoFimDeSemanaAntes(DateTime $ref): DateTime {
    $sab = sabadoDaSemana($ref);
    $w = (int)$ref->format('w');
    if ($w >= 1 && $w <= 5) $sab->modify('-7 day');
    return $sab;
}

function fimDeSemanaAtual(DateTime $ref): DateTime {
    return sabadoDaSemana($ref);
}

function proximoFimDeSemana(DateTime $ref): DateTime {
    return fimDeSemanaAtual($ref)->modify('+7 day');
}

function fmt(DateTime $d): string {
    return $d->format('Y-m-d');
}

/* =========================================================
   BANCO
========================================================= */
function getSuportes(PDO $pdo): array {
    return $pdo->query("
        SELECT id, nome, ativo
        FROM plantoes_suportes
        ORDER BY ativo DESC, nome
    ")->fetchAll();
}

function getEscalaPorSabados(PDO $pdo, array $sabados): array {
    if (!$sabados) return [];

    $ph = implode(',', array_fill(0, count($sabados), '?'));
    $st = $pdo->prepare("
        SELECT e.sabado_date, e.suporte_id, e.observacao, s.nome AS suporte_nome
        FROM plantoes_escala e
        LEFT JOIN plantoes_suportes s ON s.id = e.suporte_id
        WHERE e.sabado_date IN ($ph)
    ");
    $st->execute($sabados);

    $map = [];
    foreach ($st->fetchAll() as $r) {
        $map[$r['sabado_date']] = $r;
    }
    return $map;
}

function listSabadosDoMes(int $year, int $month): array {
    $first = new DateTime("$year-$month-01");
    $last  = (clone $first)->modify('last day of this month');

    $sabados = [];
    $d = clone $first;
    while ((int)$d->format('w') !== 6) $d->modify('+1 day');

    while ($d <= $last) {
        $sabados[] = $d->format('Y-m-d');
        $d->modify('+7 day');
    }
    return $sabados;
}

function ensureEscalaRow(PDO $pdo, string $sabado): void {
    $pdo->prepare("
        INSERT IGNORE INTO plantoes_escala (sabado_date)
        VALUES (?)
    ")->execute([$sabado]);
}

function setEscala(PDO $pdo, string $sabado, ?int $suporte, ?string $obs): void {
    ensureEscalaRow($pdo, $sabado);
    $pdo->prepare("
        UPDATE plantoes_escala
           SET suporte_id = ?, observacao = ?
         WHERE sabado_date = ?
    ")->execute([$suporte, $obs, $sabado]);
}

/* =========================================================
   ACTIONS
========================================================= */
$action = $_GET['action'] ?? '';

if ($action === 'list_suportes') {
    respond(['success' => true, 'suportes' => getSuportes($pdo)]);
}

if ($action === 'dashboard') {
    $today = new DateTime();

    $sabAnt = ultimoFimDeSemanaAntes($today);
    $sabAtu = fimDeSemanaAtual($today);
    $sabPro = proximoFimDeSemana($today);

    $ym1 = [(int)$today->format('Y'), (int)$today->format('m')];
    $next = (clone $today)->modify('first day of next month');
    $ym2 = [(int)$next->format('Y'), (int)$next->format('m')];

    $m1 = listSabadosDoMes($ym1[0], $ym1[1]);
    $m2 = listSabadosDoMes($ym2[0], $ym2[1]);

    $todos = array_values(array_unique(array_merge(
        [fmt($sabAnt), fmt($sabAtu), fmt($sabPro)],
        $m1, $m2
    )));

    $escala = getEscalaPorSabados($pdo, $todos);

    $pack = function(string $sab) use ($escala) {
        $s = new DateTime($sab);
        $d = (clone $s)->modify('+1 day');
        $r = $escala[$sab] ?? [];

        return [
            'sabado_date'  => $sab,
            'domingo_date' => $d->format('Y-m-d'),
            'label'        => $s->format('d/m').' (Sáb) + '.$d->format('d/m').' (Dom)',
            'suporte_id'   => $r['suporte_id']   ?? null,
            'suporte_nome' => $r['suporte_nome'] ?? null,
            'observacao'   => $r['observacao']   ?? null,
            'horarios' => [
                'sabado'  => HORARIO_SAB,
                'domingo' => HORARIO_DOM
            ]
        ];
    };

    respond([
        'success' => true,
        'cards' => [
            'anterior' => $pack(fmt($sabAnt)),
            'atual'    => $pack(fmt($sabAtu)),
            'proximo'  => $pack(fmt($sabPro)),
        ],
        'meses' => [
            ['year'=>$ym1[0],'month'=>$ym1[1],'sabados'=>array_map($pack,$m1)],
            ['year'=>$ym2[0],'month'=>$ym2[1],'sabados'=>array_map($pack,$m2)]
        ]
    ]);
}

respond(['success'=>false,'error'=>'Ação inválida'], 400);
