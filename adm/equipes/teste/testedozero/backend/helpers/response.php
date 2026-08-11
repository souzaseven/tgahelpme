<?php

declare(strict_types=1);

/**
 * Responde a requisição atual com JSON e encerra a execução.
 */
function respostaJson(mixed $dados, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responde com um erro padronizado em JSON.
 *
 * @param array<string, mixed> $detalhes
 */
function respostaErroJson(string $mensagem, int $status = 400, array $detalhes = []): never
{
    respostaJson([
        'sucesso'  => false,
        'erro'     => $mensagem,
        'detalhes' => $detalhes,
    ], $status);
}

/**
 * Responde com sucesso padronizado em JSON.
 */
function respostaSucessoJson(mixed $dados = [], int $status = 200): never
{
    respostaJson([
        'sucesso' => true,
        'dados'   => $dados,
    ], $status);
}
