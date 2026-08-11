<?php

declare(strict_types=1);

/**
 * Front controller — FASE 1 (Fundação).
 *
 * Estrutura de rotas propositalmente mínima aqui: apenas o suficiente para
 * provar que fundação + sessão + banco funcionam de ponta a ponta. Rotas
 * reais (login, cadastro, vagas, etc.) chegam nas próximas fases.
 */

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/helpers/session.php';
require_once __DIR__ . '/../backend/helpers/response.php';
require_once __DIR__ . '/../backend/helpers/csrf.php';

iniciarSessaoSegura();

/**
 * Detecta automaticamente o "caminho base" da aplicação a partir de onde
 * o próprio index.php está sendo executado (via SCRIPT_NAME), removendo
 * o segmento "/public" final. Isso faz o roteamento funcionar tanto em
 * staging dentro de subpasta (ex.: /Projetos/tgacarreiras/teste/) quanto
 * em produção na raiz de um domínio/subdomínio — sem precisar de nenhuma
 * configuração manual nem variável de ambiente.
 */
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$caminhoBase = preg_replace('#/public$#', '', $scriptDir);
if ($caminhoBase === '/') {
    $caminhoBase = '';
}

$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($caminhoBase !== '' && str_starts_with($caminho, $caminhoBase)) {
    $caminho = substr($caminho, strlen($caminhoBase));
}
$caminho = rtrim($caminho, '/');
if ($caminho === '') {
    $caminho = '/';
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rotas = [
    'GET' => [
        '/' => function () use ($caminhoBase): void {
            $linkHealth = htmlspecialchars($caminhoBase . '/api/health', ENT_QUOTES, 'UTF-8');
            $linkDesignSystem = htmlspecialchars($caminhoBase . '/design-system.html', ENT_QUOTES, 'UTF-8');
            echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">'
                . '<title>TGA Carreiras</title></head><body>'
                . '<h1>TGA Carreiras</h1>'
                . '<p>Fundação da plataforma configurada com sucesso (FASE 1).</p>'
                . '<p>Design System disponível (FASE 2).</p>'
                . '<p><a href="' . $linkHealth . '">Verificar status (/api/health)</a></p>'
                . '<p><a href="' . $linkDesignSystem . '">Ver Design System</a></p>'
                . '</body></html>';
        },

        // Endpoint de diagnóstico: confirma que config, sessão e banco
        // estão operacionais. Útil para verificar o deploy no servidor real.
        '/api/health' => function (): void {
            $bancoConectado = true;
            $detalheBanco = null;

            try {
                require_once __DIR__ . '/../backend/database/conexao.php';
                conectarBanco()->query('SELECT 1');
            } catch (Throwable $e) {
                $bancoConectado = false;
                $detalheBanco = APP_DEBUG ? $e->getMessage() : 'indisponível';
            }

            respostaJson([
                'sucesso'  => true,
                'app'      => 'TGA Carreiras',
                'ambiente' => APP_ENV,
                'sessao'   => session_status() === PHP_SESSION_ACTIVE,
                'banco'    => $bancoConectado ? 'conectado' : 'falha',
                'detalhe_banco' => $detalheBanco,
            ]);
        },
    ],
];

$handler = $rotas[$metodo][$caminho] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo '<h1>404</h1><p>Página não encontrada.</p>';
    exit;
}

$handler();
