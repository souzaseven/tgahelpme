<?php
// ============================================================
// api_integracao.php - Integração com o painel Evolux
// ============================================================
// 🔹 Função: capturar via scraping os operadores pausados
// 🔹 Retorno: JSON com lista de operadores, motivo e tempo
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// =============================================
// 🔧 Parâmetro de ação
// =============================================
$acao = $_GET['acao'] ?? 'listar';

// =============================================
// 🔧 Função: faz download do HTML de uma URL
// =============================================
function obterHTML($url)
{
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: TGA-Scraper/1.1\r\nAccept-Language: pt-BR\r\n",
            'timeout' => 15
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ]);

    $html = @file_get_contents($url, false, $context);
    return $html ?: null;
}

// =============================================
// 🔧 Função: extrai operadores pausados do HTML
// =============================================
function extrairPausados($html)
{
    $dados = [];
    if (!$html) return $dados;

    // Remove quebras de linha e espaços excessivos
    $html = preg_replace('/\s+/', ' ', $html);

    // Expressão regular para capturar linhas da tabela
    $pattern = '/<tr[^>]*>\s*<td[^>]*>.*?<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/i';

    if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $dados[] = [
                'operador' => trim(strip_tags($m[1])),
                'motivo'   => trim(strip_tags($m[2])),
                'duracao'  => trim(strip_tags($m[3])),
                'status'   => 'pausado'
            ];
        }
    }

    return $dados;
}

// =============================================
// 🔧 Roteamento por ação
// =============================================
switch ($acao) {
    // --------------------------------------------------------
    case 'listar':
        $url = 'https://tgasistemas.evolux.io/panel/queue?id=all#details';
        $html = obterHTML($url);

        if (!$html) {
            echo json_encode([
                'success' => false,
                'error' => 'Não foi possível acessar o painel Evolux.',
                'url' => $url
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $pausados = extrairPausados($html);

        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'quantidade' => count($pausados),
            'dados' => $pausados
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // --------------------------------------------------------
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Ação inválida.'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}
