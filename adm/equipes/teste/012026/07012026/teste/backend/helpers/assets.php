<?php
// ============================================================
// assets.php — Cache-busting de assets estáticos (CSS/JS).
// Anexa ?v=<mtime do arquivo> na URL para que o navegador (e o
// service worker) só sirvam do cache enquanto o arquivo não mudar;
// ao publicar uma alteração, o mtime muda e o asset é rebaixado
// automaticamente no próximo carregamento — sem precisar lembrar
// de subir um número de versão manualmente.
// ============================================================

if (!function_exists('asset_v')) {
    function asset_v(string $caminhoRelativo): string
    {
        $absoluto = __DIR__ . '/../../' . ltrim($caminhoRelativo, '/');
        $versao   = is_file($absoluto) ? filemtime($absoluto) : time();
        return $caminhoRelativo . '?v=' . $versao;
    }
}
