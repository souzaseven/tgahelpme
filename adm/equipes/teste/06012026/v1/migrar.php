<?php
// ============================================================
// migrar.php — Aplica migrações pendentes no banco de dados
// Acesse: /adm/planilha_controle/v1_066/migrar.php?token=fs2025
// APAGUE este arquivo após rodar com sucesso!
// ============================================================

define('TOKEN_ACESSO', 'fs2025');

if (($_GET['token'] ?? '') !== TOKEN_ACESSO) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/backend/banco/conexao.php';

$p   = TABLE_PREFIX;
$log = [];

$migracoes = [
    // Coluna valor_pago em emprestimos_parcelas (antecipação)
    "valor_pago em parcelas" => "ALTER TABLE `{$p}emprestimos_parcelas`
        ADD COLUMN IF NOT EXISTS `valor_pago` DECIMAL(15,2) DEFAULT NULL AFTER `saldo_devedor`",

    // Coluna transacao_id em emprestimos_parcelas (sincronização)
    "transacao_id em parcelas (existência)" => "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = '{$p}emprestimos_parcelas'
          AND COLUMN_NAME  = 'transacao_id'",
];

// Testa ADD COLUMN IF NOT EXISTS (MySQL 8+). Para versões antigas usa alternativa.
function addColumnSafe(PDO $pdo, string $sql, string $nome): string {
    try {
        $pdo->exec($sql);
        return "✅ OK — $nome";
    } catch (PDOException $e) {
        // Coluna já existe (código 1060) ou IF NOT EXISTS não suportado
        if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            return "⚠️ Já existe — $nome";
        }
        // MySQL < 8: tenta sem IF NOT EXISTS
        $sqlSemGuarda = preg_replace('/ADD COLUMN IF NOT EXISTS/i', 'ADD COLUMN', $sql);
        try {
            $pdo->exec($sqlSemGuarda);
            return "✅ OK (compat) — $nome";
        } catch (PDOException $e2) {
            if (strpos($e2->getMessage(), '1060') !== false) {
                return "⚠️ Já existe — $nome";
            }
            return "❌ Erro — $nome: " . $e2->getMessage();
        }
    }
}

// ── Migração 1: valor_pago ────────────────────────────────────
$log[] = addColumnSafe(
    $pdo,
    "ALTER TABLE `{$p}emprestimos_parcelas`
     ADD COLUMN IF NOT EXISTS `valor_pago` DECIMAL(15,2) DEFAULT NULL AFTER `saldo_devedor`",
    "valor_pago em emprestimos_parcelas"
);

// ── Migração 2: transacao_id (caso não exista) ────────────────
$exists = $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = '{$p}emprestimos_parcelas'
       AND COLUMN_NAME  = 'transacao_id'"
)->fetchColumn();

if (!$exists) {
    $log[] = addColumnSafe(
        $pdo,
        "ALTER TABLE `{$p}emprestimos_parcelas`
         ADD COLUMN IF NOT EXISTS `transacao_id` INT UNSIGNED DEFAULT NULL AFTER `valor_pago`",
        "transacao_id em emprestimos_parcelas"
    );
} else {
    $log[] = "⚠️ Já existe — transacao_id em emprestimos_parcelas";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Migração do Banco</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;max-width:640px;margin:auto}
h1{color:#6366f1;margin-bottom:1.5rem}
.ok{color:#10b981}.warn{color:#f59e0b}.err{color:#ef4444}
.item{padding:.4rem .75rem;margin:.25rem 0;border-radius:4px;background:#1e293b}
.aviso{margin-top:2rem;padding:1rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:6px;color:#ef4444;font-family:sans-serif;font-size:.85rem}
</style>
</head>
<body>
<h1>Migrações — FinanceOS</h1>
<?php foreach ($log as $l): ?>
<div class="item <?= str_starts_with($l,'✅') ? 'ok' : (str_starts_with($l,'⚠️') ? 'warn' : 'err') ?>">
    <?= htmlspecialchars($l) ?>
</div>
<?php endforeach ?>
<div class="aviso">
    <strong>⚠️ IMPORTANTE:</strong> Após verificar que todas as migrações rodaram com sucesso,
    <strong>apague este arquivo</strong> do servidor imediatamente.
</div>
</body>
</html>
