<?php
// ============================================================
// registrar_fila.php
// Registra entrada do operador na fila (histórico)
// ============================================================

if (!isset($operadorId, $operadorNome, $equipe)) {
    return; // segurança
}

$stmt = $pdo->prepare("
    INSERT INTO log_fila (
        operador_id,
        operador_nome,
        equipe,
        inicio,
        data
    ) VALUES (
        :id,
        :nome,
        :equipe,
        NOW(),
        CURDATE()
    )
");

$stmt->execute([
    ":id"     => $operadorId,
    ":nome"   => $operadorNome,
    ":equipe" => $equipe
]);
