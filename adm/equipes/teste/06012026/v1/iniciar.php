<?php
// ============================================================
// iniciar.php — Configuração inicial do banco (FinanceOS)
// Acesse: /adm/planilha_controle/v1/iniciar.php?token=fs2025
// APAGUE este arquivo após rodar com sucesso!
// ============================================================

define('TOKEN_ACESSO', 'fs2025');

if (($_GET['token'] ?? '') !== TOKEN_ACESSO) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/backend/banco/conexao.php';

$p   = TABLE_PREFIX;
$log = [];
$ok  = 0;
$ja  = 0;
$err = [];

// ─── Tabelas ──────────────────────────────────────────────────────────────────
$tabelas = [];

$tabelas['configuracoes'] =
    "CREATE TABLE IF NOT EXISTS `{$p}configuracoes` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `chave`         VARCHAR(100)  NOT NULL UNIQUE,
        `valor`         TEXT,
        `descricao`     VARCHAR(255),
        `criado_em`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['categorias'] =
    "CREATE TABLE IF NOT EXISTS `{$p}categorias` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`          VARCHAR(100) NOT NULL,
        `tipo`          ENUM('receita','despesa','ambos') NOT NULL DEFAULT 'despesa',
        `icone`         VARCHAR(50)  DEFAULT 'tag',
        `cor`           VARCHAR(7)   DEFAULT '#6366f1',
        `categoria_pai` INT UNSIGNED DEFAULT NULL,
        `ordem`         TINYINT UNSIGNED DEFAULT 0,
        `ativo`         TINYINT(1)  DEFAULT 1,
        `criado_em`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`categoria_pai`) REFERENCES `{$p}categorias`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['contas'] =
    "CREATE TABLE IF NOT EXISTS `{$p}contas` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`          VARCHAR(100) NOT NULL,
        `tipo`          ENUM('corrente','poupanca','carteira','investimento','outro') NOT NULL DEFAULT 'corrente',
        `banco`         VARCHAR(100),
        `agencia`       VARCHAR(20),
        `numero`        VARCHAR(30),
        `saldo_inicial` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `saldo_atual`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `cor`           VARCHAR(7)   DEFAULT '#6366f1',
        `icone`         VARCHAR(50)  DEFAULT 'university',
        `incluir_total` TINYINT(1)  DEFAULT 1,
        `ativo`         TINYINT(1)  DEFAULT 1,
        `criado_em`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['cartoes'] =
    "CREATE TABLE IF NOT EXISTS `{$p}cartoes` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`            VARCHAR(100) NOT NULL,
        `bandeira`        ENUM('visa','mastercard','elo','amex','hipercard','outro') DEFAULT 'visa',
        `banco`           VARCHAR(100),
        `ultimos_digitos` CHAR(4),
        `limite_total`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `limite_usado`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `dia_fechamento`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `dia_vencimento`  TINYINT UNSIGNED NOT NULL DEFAULT 10,
        `cor`             VARCHAR(7)   DEFAULT '#8b5cf6',
        `ativo`           TINYINT(1)  DEFAULT 1,
        `criado_em`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['cartoes_faturas'] =
    "CREATE TABLE IF NOT EXISTS `{$p}cartoes_faturas` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `cartao_id`       INT UNSIGNED NOT NULL,
        `mes_referencia`  TINYINT UNSIGNED NOT NULL,
        `ano_referencia`  YEAR NOT NULL,
        `data_fechamento` DATE NOT NULL,
        `data_vencimento` DATE NOT NULL,
        `valor_total`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `valor_pago`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `status`          ENUM('aberta','fechada','paga','parcial') DEFAULT 'aberta',
        `criado_em`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_fatura` (`cartao_id`,`mes_referencia`,`ano_referencia`),
        FOREIGN KEY (`cartao_id`) REFERENCES `{$p}cartoes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['transacoes'] =
    "CREATE TABLE IF NOT EXISTS `{$p}transacoes` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `tipo`             ENUM('receita','despesa','transferencia') NOT NULL,
        `descricao`        VARCHAR(255) NOT NULL,
        `valor`            DECIMAL(15,2) NOT NULL,
        `data`             DATE NOT NULL,
        `categoria_id`     INT UNSIGNED DEFAULT NULL,
        `conta_id`         INT UNSIGNED DEFAULT NULL,
        `conta_destino_id` INT UNSIGNED DEFAULT NULL,
        `cartao_id`        INT UNSIGNED DEFAULT NULL,
        `fatura_id`        INT UNSIGNED DEFAULT NULL,
        `recorrencia_id`   INT UNSIGNED DEFAULT NULL,
        `parcela_atual`    SMALLINT UNSIGNED DEFAULT NULL,
        `parcela_total`    SMALLINT UNSIGNED DEFAULT NULL,
        `status`           ENUM('pendente','pago','cancelado') DEFAULT 'pago',
        `observacao`       TEXT,
        `tags`             VARCHAR(255),
        `comprovante_path` VARCHAR(500),
        `criado_em`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_data`      (`data`),
        INDEX `idx_tipo`      (`tipo`),
        INDEX `idx_categoria` (`categoria_id`),
        INDEX `idx_conta`     (`conta_id`),
        INDEX `idx_cartao`    (`cartao_id`),
        FOREIGN KEY (`categoria_id`)     REFERENCES `{$p}categorias`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`conta_id`)         REFERENCES `{$p}contas`(`id`)     ON DELETE SET NULL,
        FOREIGN KEY (`conta_destino_id`) REFERENCES `{$p}contas`(`id`)     ON DELETE SET NULL,
        FOREIGN KEY (`cartao_id`)        REFERENCES `{$p}cartoes`(`id`)    ON DELETE SET NULL,
        FOREIGN KEY (`fatura_id`)        REFERENCES `{$p}cartoes_faturas`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['recorrencias'] =
    "CREATE TABLE IF NOT EXISTS `{$p}recorrencias` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `descricao`      VARCHAR(255) NOT NULL,
        `tipo`           ENUM('receita','despesa') NOT NULL,
        `valor`          DECIMAL(15,2) NOT NULL,
        `categoria_id`   INT UNSIGNED DEFAULT NULL,
        `conta_id`       INT UNSIGNED DEFAULT NULL,
        `cartao_id`      INT UNSIGNED DEFAULT NULL,
        `frequencia`     ENUM('diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') DEFAULT 'mensal',
        `dia_vencimento` TINYINT UNSIGNED DEFAULT 1,
        `data_inicio`    DATE NOT NULL,
        `data_fim`       DATE DEFAULT NULL,
        `ativo`          TINYINT(1) DEFAULT 1,
        `gerar_ate`      DATE DEFAULT NULL,
        `criado_em`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`categoria_id`) REFERENCES `{$p}categorias`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`conta_id`)     REFERENCES `{$p}contas`(`id`)     ON DELETE SET NULL,
        FOREIGN KEY (`cartao_id`)    REFERENCES `{$p}cartoes`(`id`)    ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['emprestimos'] =
    "CREATE TABLE IF NOT EXISTS `{$p}emprestimos` (
        `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`             VARCHAR(150) NOT NULL,
        `tipo`             ENUM('pessoal','veiculo','imovel','consignado','cartao','outro') DEFAULT 'pessoal',
        `credor`           VARCHAR(100),
        `valor_contratado` DECIMAL(15,2) NOT NULL,
        `valor_parcela`    DECIMAL(15,2) NOT NULL,
        `total_parcelas`   SMALLINT UNSIGNED NOT NULL,
        `parcelas_pagas`   SMALLINT UNSIGNED DEFAULT 0,
        `taxa_juros_mensal`DECIMAL(8,4)  NOT NULL DEFAULT 0.0000,
        `cet_mensal`       DECIMAL(8,4)  DEFAULT NULL,
        `saldo_devedor`    DECIMAL(15,2) NOT NULL,
        `data_inicio`      DATE NOT NULL,
        `data_fim`         DATE NOT NULL,
        `dia_vencimento`   TINYINT UNSIGNED DEFAULT 10,
        `conta_debito_id`  INT UNSIGNED DEFAULT NULL,
        `status`           ENUM('ativo','quitado','atrasado','negociado') DEFAULT 'ativo',
        `observacao`       TEXT,
        `criado_em`        DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`conta_debito_id`) REFERENCES `{$p}contas`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['emprestimos_parcelas'] =
    "CREATE TABLE IF NOT EXISTS `{$p}emprestimos_parcelas` (
        `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `emprestimo_id`     INT UNSIGNED NOT NULL,
        `numero`            SMALLINT UNSIGNED NOT NULL,
        `data_vencimento`   DATE NOT NULL,
        `data_pagamento`    DATE DEFAULT NULL,
        `valor_parcela`     DECIMAL(15,2) NOT NULL,
        `valor_amortizacao` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `valor_juros`       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `saldo_devedor`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `valor_pago`        DECIMAL(15,2) DEFAULT NULL,
        `status`            ENUM('pendente','pago','atrasado','antecipado') DEFAULT 'pendente',
        `transacao_id`      INT UNSIGNED DEFAULT NULL,
        FOREIGN KEY (`emprestimo_id`) REFERENCES `{$p}emprestimos`(`id`)  ON DELETE CASCADE,
        FOREIGN KEY (`transacao_id`)  REFERENCES `{$p}transacoes`(`id`)   ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['investimentos'] =
    "CREATE TABLE IF NOT EXISTS `{$p}investimentos` (
        `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`               VARCHAR(150) NOT NULL,
        `tipo`               ENUM('tesouro','cdb','lci','lca','lc','debenture','fundo','acao','fii','etf','cripto','poupanca','outro') NOT NULL,
        `ticker`             VARCHAR(20),
        `corretora`          VARCHAR(100),
        `valor_aplicado`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `valor_atual`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `quantidade`         DECIMAL(15,8) DEFAULT NULL,
        `rentabilidade_tipo` ENUM('prefixado','pos_cdi','pos_ipca','pos_selic','variavel') DEFAULT 'pos_cdi',
        `rentabilidade_taxa` DECIMAL(8,4)  DEFAULT NULL,
        `data_aplicacao`     DATE NOT NULL,
        `data_vencimento`    DATE DEFAULT NULL,
        `liquidez`           ENUM('diaria','30d','60d','90d','180d','365d','vencimento') DEFAULT 'vencimento',
        `objetivo_id`        INT UNSIGNED DEFAULT NULL,
        `conta_id`           INT UNSIGNED DEFAULT NULL,
        `ativo`              TINYINT(1) DEFAULT 1,
        `criado_em`          DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['investimentos_transacoes'] =
    "CREATE TABLE IF NOT EXISTS `{$p}investimentos_transacoes` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `investimento_id` INT UNSIGNED NOT NULL,
        `tipo`            ENUM('aporte','resgate','dividendo','juros','rendimento','taxa','outro') NOT NULL,
        `valor`           DECIMAL(15,2) NOT NULL,
        `quantidade`      DECIMAL(15,8) DEFAULT NULL,
        `preco_unitario`  DECIMAL(15,8) DEFAULT NULL,
        `data`            DATE NOT NULL,
        `descricao`       VARCHAR(255),
        `criado_em`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`investimento_id`) REFERENCES `{$p}investimentos`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['metas'] =
    "CREATE TABLE IF NOT EXISTS `{$p}metas` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `nome`          VARCHAR(150) NOT NULL,
        `descricao`     TEXT,
        `tipo`          ENUM('emergencia','carro','imovel','viagem','aposentadoria','educacao','quitar_divida','outro') DEFAULT 'outro',
        `valor_alvo`    DECIMAL(15,2) NOT NULL,
        `valor_atual`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `aporte_mensal` DECIMAL(15,2) DEFAULT NULL,
        `data_inicio`   DATE NOT NULL,
        `data_prazo`    DATE DEFAULT NULL,
        `prioridade`    TINYINT UNSIGNED DEFAULT 3,
        `cor`           VARCHAR(7)   DEFAULT '#10b981',
        `icone`         VARCHAR(50)  DEFAULT 'bullseye',
        `status`        ENUM('ativa','pausada','concluida','cancelada') DEFAULT 'ativa',
        `criado_em`     DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['metas_aportes'] =
    "CREATE TABLE IF NOT EXISTS `{$p}metas_aportes` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `meta_id`      INT UNSIGNED NOT NULL,
        `valor`        DECIMAL(15,2) NOT NULL,
        `data`         DATE NOT NULL,
        `descricao`    VARCHAR(255),
        `transacao_id` INT UNSIGNED DEFAULT NULL,
        `criado_em`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`meta_id`)      REFERENCES `{$p}metas`(`id`)      ON DELETE CASCADE,
        FOREIGN KEY (`transacao_id`) REFERENCES `{$p}transacoes`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['orcamentos'] =
    "CREATE TABLE IF NOT EXISTS `{$p}orcamentos` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `categoria_id`    INT UNSIGNED NOT NULL,
        `mes`             TINYINT UNSIGNED NOT NULL,
        `ano`             YEAR NOT NULL,
        `valor_planejado` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `valor_gasto`     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `criado_em`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_orcamento` (`categoria_id`,`mes`,`ano`),
        FOREIGN KEY (`categoria_id`) REFERENCES `{$p}categorias`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tabelas['alertas'] =
    "CREATE TABLE IF NOT EXISTS `{$p}alertas` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `tipo`            ENUM('vencimento','limite_cartao','orcamento','meta','emprestimo','saldo','custom') NOT NULL,
        `titulo`          VARCHAR(150) NOT NULL,
        `mensagem`        TEXT NOT NULL,
        `nivel`           ENUM('info','aviso','urgente') DEFAULT 'aviso',
        `referencia_tipo` VARCHAR(50)  DEFAULT NULL,
        `referencia_id`   INT UNSIGNED DEFAULT NULL,
        `data_alerta`     DATE DEFAULT NULL,
        `lido`            TINYINT(1) DEFAULT 0,
        `criado_em`       DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// ─── Executa ──────────────────────────────────────────────────────────────────
foreach ($tabelas as $nome => $sql) {
    try {
        $existe = $pdo->query("SHOW TABLES LIKE '{$p}{$nome}'")->rowCount() > 0;
        $pdo->exec($sql);
        $log[] = ['s' => $existe ? 'ja' : 'ok', 't' => $p . $nome];
        $existe ? $ja++ : $ok++;
    } catch (PDOException $e) {
        $log[] = ['s' => 'err', 't' => $p . $nome, 'm' => $e->getMessage()];
        $err[] = $nome;
    }
}

// ─── Migrações (adiciona colunas em tabelas existentes) ──────────────────────
$migracoes = [
    "ALTER TABLE `{$p}emprestimos_parcelas` ADD COLUMN IF NOT EXISTS `valor_pago` DECIMAL(15,2) DEFAULT NULL AFTER `saldo_devedor`",
];
foreach ($migracoes as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* ignora se já existe */ }
}

// ─── Categorias padrão ────────────────────────────────────────────────────────
$cats = [
    ['Salário',         'receita', 'wallet',         '#10b981'],
    ['Renda Extra',     'receita', 'coins',          '#06b6d4'],
    ['Investimentos',   'receita', 'chart-line',     '#8b5cf6'],
    ['Aluguéis',        'receita', 'home',           '#f59e0b'],
    ['Reembolsos',      'receita', 'undo',           '#6366f1'],
    ['Outros Receitas', 'receita', 'plus-circle',    '#64748b'],
    ['Moradia',         'despesa', 'home',           '#ef4444'],
    ['Alimentação',     'despesa', 'utensils',       '#f97316'],
    ['Transporte',      'despesa', 'car',            '#eab308'],
    ['Saúde',           'despesa', 'heart-pulse',    '#ec4899'],
    ['Educação',        'despesa', 'graduation-cap', '#8b5cf6'],
    ['Lazer',           'despesa', 'gamepad',        '#06b6d4'],
    ['Vestuário',       'despesa', 'shirt',          '#84cc16'],
    ['Assinaturas',     'despesa', 'repeat',         '#6366f1'],
    ['Financiamentos',  'despesa', 'landmark',       '#dc2626'],
    ['Outros Despesas', 'despesa', 'tag',            '#64748b'],
];

$stmt    = $pdo->prepare("INSERT IGNORE INTO `{$p}categorias` (`nome`,`tipo`,`icone`,`cor`) VALUES (?,?,?,?)");
$catOk   = 0;
foreach ($cats as [$n, $t, $i, $c]) {
    $stmt->execute([$n, $t, $i, $c]);
    if ($stmt->rowCount() > 0) $catOk++;
}

// ─── Saída HTML ───────────────────────────────────────────────────────────────
$semErros = empty($err);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FinanceOS — Setup</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .box{background:#1e293b;border-radius:1rem;padding:2rem;max-width:640px;width:100%;border:1px solid #334155}
  h1{font-size:1.4rem;color:#f8fafc;margin-bottom:.25rem}
  .sub{color:#94a3b8;font-size:.85rem;margin-bottom:1.5rem}
  .linha{display:flex;align-items:center;gap:.75rem;padding:.5rem .75rem;border-radius:.5rem;margin-bottom:.35rem;font-size:.875rem}
  .ok {background:#052e16;color:#4ade80}.ok  span{color:#22c55e}
  .ja {background:#1c1917;color:#a8a29e}.ja  span{color:#78716c}
  .er {background:#450a0a;color:#fca5a5}.er  span{color:#ef4444}
  .resumo{margin-top:1.5rem;padding:1rem;background:#0f172a;border-radius:.75rem;display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;text-align:center}
  .resumo div{padding:.5rem;border-radius:.5rem}
  .resumo .n{font-size:1.5rem;font-weight:700}
  .resumo .l{font-size:.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em}
  .r-ok{background:#052e16;color:#4ade80}
  .r-ja{background:#1c1917;color:#a8a29e}
  .r-er{background:#450a0a;color:#ef4444}
  .aviso{margin-top:1rem;padding:.75rem 1rem;border-radius:.5rem;font-size:.8rem;text-align:center}
  .aviso.verde{background:#052e16;color:#86efac}
  .aviso.vermelho{background:#450a0a;color:#fca5a5}
  .apagar{margin-top:1rem;padding:.75rem 1rem;background:#1e293b;border:1px dashed #ef4444;border-radius:.5rem;font-size:.8rem;color:#fca5a5;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h1>FinanceOS — Setup do Banco</h1>
  <div class="sub"><?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;·&nbsp; Prefixo: <code style="color:#818cf8"><?= TABLE_PREFIX ?></code></div>

  <?php foreach ($log as $item): ?>
    <?php
      $cls  = match($item['s']) { 'ok' => 'ok', 'ja' => 'ja', default => 'er' };
      $icon = match($item['s']) { 'ok' => '✓', 'ja' => '–', default => '✗' };
      $acao = match($item['s']) { 'ok' => 'criada', 'ja' => 'já existe', default => 'ERRO' };
    ?>
    <div class="linha <?= $cls ?>">
      <span><?= $icon ?></span>
      <strong style="flex:1"><?= htmlspecialchars($item['t']) ?></strong>
      <?= $acao ?>
      <?php if (!empty($item['m'])): ?>
        &nbsp;— <?= htmlspecialchars($item['m']) ?>
      <?php endif ?>
    </div>
  <?php endforeach ?>

  <div class="resumo">
    <div class="r-ok"><div class="n"><?= $ok ?></div><div class="l">Criadas</div></div>
    <div class="r-ja"><div class="n"><?= $ja ?></div><div class="l">Já existiam</div></div>
    <div class="r-er"><div class="n"><?= count($err) ?></div><div class="l">Erros</div></div>
  </div>

  <?php if ($catOk > 0): ?>
    <div class="aviso verde" style="margin-top:.75rem">
      <?= $catOk ?> categoria(s) padrão inserida(s)
    </div>
  <?php endif ?>

  <?php if ($semErros): ?>
    <div class="aviso verde">Banco configurado com sucesso! O sistema está pronto.</div>
  <?php else: ?>
    <div class="aviso vermelho">Atenção: <?= count($err) ?> tabela(s) com erro. Verifique as FKs e tente novamente.</div>
  <?php endif ?>

  <div class="apagar">
    ⚠️ Apague o arquivo <strong>iniciar.php</strong> após concluir a configuração.
  </div>
</div>
</body>
</html>
