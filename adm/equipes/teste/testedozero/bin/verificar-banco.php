<?php

declare(strict_types=1);

/**
 * Script de linha de comando para testar a conexão com o banco de dados
 * usando as credenciais definidas em .env.
 *
 * Uso:
 *   php bin/verificar-banco.php
 */

require_once __DIR__ . '/../backend/database/conexao.php';

echo "TGA Carreiras — verificação de conexão com o banco" . PHP_EOL;
echo str_repeat('-', 52) . PHP_EOL;

try {
    $pdo = conectarBanco();
    $pdo->query('SELECT 1');
    echo '[OK] Conexão estabelecida com sucesso.' . PHP_EOL;

    $tabelas = ['usuarios_carreiras', 'empresas_carreiras', 'vagas', 'candidaturas'];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS existe FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($tabela)
        );
        $existe = (int) $stmt->fetchColumn() > 0;
        echo sprintf('[%s] tabela "%s"' . PHP_EOL, $existe ? 'OK' : '--', $tabela);
    }
} catch (Throwable $e) {
    echo '[FALHA] ' . $e->getMessage() . PHP_EOL;
    echo 'Verifique se o arquivo .env existe na raiz do projeto e se as' . PHP_EOL;
    echo 'credenciais (DB_HOST, DB_NAME, DB_USER, DB_PASS) estão corretas.' . PHP_EOL;
    exit(1);
}
