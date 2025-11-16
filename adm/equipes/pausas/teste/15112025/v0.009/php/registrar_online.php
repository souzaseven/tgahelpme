<?php
/**
 * registrar_online.php
 * ------------------------------------------------------------
 * Registra o operador como ONLINE imediatamente após login.
 *
 * - Não altera status atual do operador (ativo/pausa/fila/etc.)
 * - Apenas cria o registro se não existir
 * - Permite que o frontend mostre “Online” no card
 * - Retorno segue o padrão JSON do sistema
 * ------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . "/conexao.php";

// Dados recebidos
$nome   = trim($_POST['nome']   ?? '');
$equipe = trim($_POST['equipe'] ?? '');

if ($nome === '' || $equipe === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Nome e equipe são obrigatórios.'
    ]);
    exit;
}

try {
    // Verifica se já existe o operador
    $st = $pdo->prepare("
        SELECT id FROM controle_pausa 
        WHERE nome_usuario = ? AND equipe = ?
        LIMIT 1
    ");
    $st->execute([$nome, $equipe]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    // Se não existir → cria registro
    if (!$row) {
        $stInsert = $pdo->prepare("
            INSERT INTO controle_pausa (nome_usuario, equipe, status, ultima_atualizacao)
            VALUES (?, ?, 'ativo', NOW())
        ");
        $stInsert->execute([$nome, $equipe]);
    }

    // Marca como ONLINE (sem last_seen, apenas para lógica do frontend)
    // Não mexe no status do operador
    echo json_encode([
        'success' => true,
        'msg'     => 'Operador registrado como ONLINE.',
        'nome'    => $nome,
        'equipe'  => $equipe,
        'online'  => true
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao registrar operador como online.',
        'detalhe' => $e->getMessage()
    ]);
}
