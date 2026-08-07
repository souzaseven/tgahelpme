<?php

/**
 * Valida uma cor no formato hexadecimal (#rrggbb). Usado por todo
 * cadastro que tem cor de avatar/etiqueta (terceiros, contas, cartões,
 * categorias, responsáveis, metas). Se o valor recebido não for uma cor
 * hex válida, devolve a cor padrão do módulo que chamou.
 */
function corOuPadrao(?string $cor, string $padrao = '#6366f1'): string
{
    return ($cor !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) ? $cor : $padrao;
}

/**
 * Normaliza o nome de um ícone (Font Awesome) recebido do formulário:
 * minúsculo, sem espaços, só letras/números/hífen. Se ficar vazio depois
 * de limpar, devolve o ícone padrão do módulo que chamou.
 */
function iconeOuPadrao(?string $icone, string $padrao = 'tag'): string
{
    $limpo = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string) $icone)));
    return $limpo !== '' ? $limpo : $padrao;
}

/**
 * Normaliza texto pra comparação (minúsculo, sem acento, sem espaços nas
 * pontas). Usado pela "chave" dos atalhos de preenchimento automático —
 * ver backend/api/atalhos.php — pra "Oferta Igreja" e "oferta igreja"
 * baterem com a mesma regra.
 */
function normalizarTexto(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $com = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
    $sem = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    return str_replace($com, $sem, $s);
}
