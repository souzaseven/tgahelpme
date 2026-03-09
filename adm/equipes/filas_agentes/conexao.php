<?php
// ============================================================
// conexao.php - Conexão central com banco (FASE 6)
// ============================================================
//
// - Único ponto de conexão PDO
// - Usado por todos os endpoints (login, painel, fila, pausa)
// - Sem nada específico da Fase 7
// ============================================================

// Impede acesso direto ao arquivo
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(["erro" => "Acesso direto não permitido."], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// CONFIGURAÇÃO DO AMBIENTE
// ============================================================
// Altere para "teste" quando for usar banco local
$AMBIENTE = "producao"; // "teste" ou "producao"

// ============================================================
// CREDENCIAIS (IDEAL → mover para .env ou outro local seguro)
// ============================================================
$CREDENCIAIS = [
    "producao" => [
        "host"     => "108.167.151.50",
        "dbname"   => "tgamea80_SUPORTE",
        "user"     => "tgamea80_tgamea80",
        "password" => "anderson@2250"
    ],
    "teste" => [
        "host"     => "localhost",
        "dbname"   => "tga_teste",
        "user"     => "root",
        "password" => ""
    ]
];

$cfg = $CREDENCIAIS[$AMBIENTE] ?? $CREDENCIAIS["teste"];

// ============================================================
// CONEXÃO PDO
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
        $cfg['user'],
        $cfg['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'"
        ]
    );

} catch (PDOException $e) {

    // Segurança: erro detalhado apenas em ambiente de teste
    $msgErro = ($AMBIENTE === "teste") ? $e->getMessage() : "Erro interno.";

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "erro"    => "Falha ao conectar ao banco de dados.",
        "detalhe" => $msgErro
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ============================================================
// Função utilitária para respostas JSON
// ============================================================
function respostaJSON(array $array)
{
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
