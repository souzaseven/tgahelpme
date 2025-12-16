<?php
/**
 * registrar_pausa.php
 * ---------------------------------------------
 * Registra o INÍCIO de uma pausa no histórico.
 * NÃO calcula duração.
 * NÃO fecha pausa.
 * NÃO retorna nada ao frontend.
 *
 * Pode ser incluído com require_once.
 */

if (!isset($pdo)) {
    // Garante que a conexão exista
    require_once __DIR__ . "/../conexao.php";
}

// ------------------------------------------------
// Dados obrigatórios (esperados do fluxo atual)
// ------------------------------------------------
$operadorId   = $operadorId   ?? $_POST['operador_id']   ?? null;
$operadorNome = $operadorNome ?? $_POST['operador_nome'] ?? null;
$equipe       = $equipe       ?? $_POST['equipe']        ?? null;
$tipoPausa    = $tipoPausa    ?? $_POST['tipo_pausa']    ?? 'pausa';

if (!$operadorId || !$operadorNome || !$equipe) {
    // Não quebra o sistema principal
    return;
}

try {

    // ------------------------------------------------
    // Evita duplicar pausa aberta
    // ------------------------------------------------
    $check = $pdo->prepare("
        SELECT id 
        FROM log_pausas
        WHERE operador_id = :operador_id
          AND fim IS NULL
          AND data = CURDATE()
        LIMIT 1
    ");
    $check->execute([
        ':operador_id' => $operadorId
    ]);

    if ($check->fetch()) {
        // Já existe pausa aberta → não registra outra
        return;
    }

    // ------------------------------------------------
    // Registrar início da pausa
    // ------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO log_pausas (
            operador_id,
            operador_nome,
            equipe,
            tipo_pausa,
            inicio,
            data
        ) VALUES (
            :operador_id,
            :operador_nome,
            :equipe,
            :tipo_pausa,
            NOW(),
            CURDATE()
        )
    ");

    $stmt->execute([
        ':operador_id'   => $operadorId,
        ':operador_nome' => $operadorNome,
        ':equipe'        => $equipe,
        ':tipo_pausa'    => $tipoPausa
    ]);

} catch (Exception $e) {
    // Erros aqui NÃO devem quebrar o fluxo principal
    // Se quiser logar no futuro:
    // file_put_contents(__DIR__.'/../logs/erro_pausa.log', $e->getMessage(), FILE_APPEND);
}
