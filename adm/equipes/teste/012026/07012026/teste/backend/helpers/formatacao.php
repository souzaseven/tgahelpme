<?php
// ============================================================
// formatacao.php — Formatação de valores monetários/percentuais
// usada pelas páginas (pages/*.php). Antes cada página redeclarava
// sua própria função brl()/pct() com nomes diferentes (brlI, brlE,
// brlC, brlR, brlM...) para evitar colisão — centralizado aqui.
// ============================================================

if (!function_exists('brl')) {
    function brl(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }
}

if (!function_exists('pct')) {
    function pct(float $v, int $casas = 1): string
    {
        return number_format($v, $casas, ',', '.') . '%';
    }
}
