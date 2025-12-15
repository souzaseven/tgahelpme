<?php
/**
 * backend/registrar_log.php
 *
 * Gera arquivos de log TXT, separados por dia, na pasta /logs.
 * Cada linha registra:
 *  - data/hora
 *  - IP
 *  - operador_id / operador_nome / equipe
 *  - ação
 *  - detalhes (JSON)
 */

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

// Dados recebidos
$acao         = $_POST['acao']         ?? '';
$detalhesRaw  = $_POST['detalhes']     ?? '';
$operadorId   = $_POST['operador_id']  ?? '';
$operadorNome = $_POST['operador_nome']?? '';
$equipe       = $_POST['equipe']       ?? '';

if (is_array($detalhesRaw)) {
    $detalhes = json_encode($detalhesRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    $detalhes = (string) $detalhesRaw;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '-';

$linha = sprintf(
    "[%s] IP:%s | operador_id:%s | operador_nome:%s | equipe:%s | acao:%s | detalhes:%s\n",
    date('Y-m-d H:i:s'),
    $ip,
    $operadorId,
    $operadorNome,
    $equipe,
    $acao,
    $detalhes
);

// Pasta de logs (ajuste se quiser outra localização)
$dirLogs = __DIR__ . '/../logs';

if (!is_dir($dirLogs)) {
    @mkdir($dirLogs, 0775, true);
}

$arquivo = $dirLogs . '/controle_pausa_' . date('Y-m-d') . '.log';

try {
    file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'erro'    => 'Falha ao gravar log',
        'detalhe' => $e->getMessage()
    ]);
}
