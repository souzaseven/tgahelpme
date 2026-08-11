<?php

declare(strict_types=1);

/**
 * Ponto central de conexão com o banco de dados (PDO + MySQL + utf8mb4).
 *
 * Credenciais vêm exclusivamente de variáveis de ambiente (.env):
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 * Nunca coloque credenciais diretamente neste arquivo.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Retorna uma conexão PDO única (singleton) para toda a requisição.
 *
 * @throws RuntimeException se a configuração estiver ausente ou a conexão falhar.
 */
function conectarBanco(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = (string) env('DB_HOST', '127.0.0.1');
    $porta   = (string) env('DB_PORT', '3306');
    $nome    = env('DB_NAME');
    $usuario = env('DB_USER');
    $senha   = (string) env('DB_PASS', '');

    if (!$nome || !$usuario) {
        registrarErro('Configuração de banco ausente: verifique DB_HOST, DB_NAME, DB_USER, DB_PASS no .env');
        throw new RuntimeException('Configuração de banco de dados ausente.');
    }

    $dsn = "mysql:host={$host};port={$porta};dbname={$nome};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, (string) $usuario, $senha, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
    } catch (PDOException $e) {
        // Nunca expor host/usuário/senha/detalhes do driver para o cliente.
        registrarErro('Falha na conexão com o banco de dados: ' . $e->getMessage());
        throw new RuntimeException('Não foi possível conectar ao banco de dados.');
    }

    return $pdo;
}
