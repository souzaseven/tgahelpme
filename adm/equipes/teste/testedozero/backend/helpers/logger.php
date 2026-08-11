<?php

declare(strict_types=1);

/**
 * Logger simples em arquivo para a FASE 1 (fundação).
 *
 * Este logger é intencionalmente baseado em arquivo, sem dependência de
 * banco. A tabela "logs_sistema" (para auditoria de ações administrativas)
 * será criada apenas na FASE 10, quando fizer sentido registrar eventos de
 * negócio no banco. Este arquivo cobre apenas erros técnicos da aplicação.
 *
 * IMPORTANTE: nunca registrar senha, token completo ou dados sensíveis aqui.
 */

/**
 * Registra uma linha de erro/evento técnico em backend/storage/logs/.
 *
 * @param array<string, mixed> $contexto
 */
function registrarErro(string $mensagem, array $contexto = []): void
{
    $diretorio = TGA_ROOT . '/backend/storage/logs';

    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0775, true);
    }

    $linhaContexto = '';
    if ($contexto !== []) {
        $linhaContexto = ' | contexto: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
    }

    $linha = sprintf('[%s] %s%s%s', date('Y-m-d H:i:s'), $mensagem, $linhaContexto, PHP_EOL);

    file_put_contents(
        $diretorio . '/app-' . date('Y-m-d') . '.log',
        $linha,
        FILE_APPEND | LOCK_EX
    );
}
