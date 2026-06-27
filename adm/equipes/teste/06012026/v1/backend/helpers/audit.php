<?php

/**
 * Registra uma entrada no log de auditoria.
 *
 * @param PDO    $pdo
 * @param string $prefixo   TABLE_PREFIX
 * @param string $tabela    Nome da tabela (sem prefixo)
 * @param int    $id        ID do registro afetado
 * @param string $acao      'insert' | 'update' | 'delete'
 * @param array|null $antes Snapshot antes da mudança
 * @param array|null $depois Snapshot depois da mudança
 */
function registrarAudit(
    PDO    $pdo,
    string $prefixo,
    string $tabela,
    int    $id,
    string $acao,
    ?array $antes  = null,
    ?array $depois = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare(
            "INSERT INTO `{$prefixo}audit_log`
             (tabela, registro_id, acao, dados_antes, dados_depois, ip)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $tabela,
            $id,
            $acao,
            $antes  !== null ? json_encode($antes,  JSON_UNESCAPED_UNICODE) : null,
            $depois !== null ? json_encode($depois, JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    } catch (Throwable) {
        // Auditoria nunca deve interromper a operação principal
    }
}
