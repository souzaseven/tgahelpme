<?php
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso restrito a administradores.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../backend/conexao.php';

// "desde" = timestamp da notificação mais recente que o usuário já viu
// (mandado pelo front, guardado no localStorage dele). Assim nada que mudou
// enquanto o admin estava ausente (reunião, almoço, etc.) fica de fora —
// antes a janela era sempre fixa de 10 minutos e o que passasse disso sumia
// sem nunca ter sido mostrado.
//
// Sem "desde" (primeira vez que o painel é aberto nesse navegador), cai no
// comportamento antigo: últimos 10 minutos.
$minutosPadrao = 10;
$inicio = date('Y-m-d H:i:s', strtotime("-$minutosPadrao minutes"));

$desde = $_GET['desde'] ?? '';
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $desde)) {
    // Nunca busca mais que 7 dias pra trás, mesmo que o "desde" do cliente
    // seja muito antigo (evita varrer um histórico enorme sem necessidade)
    $limiteMaximo = date('Y-m-d H:i:s', strtotime('-7 days'));
    $inicio = max($desde, $limiteMaximo);
}

$sql = "SELECT id, operador_id, nome_operador, lider, semana, alterado_em
        FROM repasse_historico
        WHERE alterado_em > :inicio
        ORDER BY alterado_em DESC
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':inicio', $inicio);
$stmt->execute();

$novos = $stmt->fetchAll();

echo json_encode(['novos' => $novos], JSON_UNESCAPED_UNICODE);
