<?php
// Configuração inicial
set_time_limit(60);
header('Content-Type: application/json');

// Conexão com banco
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 10,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_PERSISTENT => false
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão com banco']);
    exit;
}

// Lista de palavras-chave para rastrear
$palavras_chave = ['erro', 'rejeição', 'xml', 'nfe', 'danfe', 'firebird', 'configurar', 'pdv'];

// Páginas padrão
$paginas = [
    'https://tgameajuda.com/perguntas-frequentes.html',
    'https://tgameajuda.com/videos.html',
    'https://tgameajuda.com/listar_pdf.php'
];

// Permite passar ?url=https://site.com/extra.html
if (!empty($_GET['url']) && filter_var($_GET['url'], FILTER_VALIDATE_URL)) {
    $paginas[] = $_GET['url'];
}

// Função simples de scraping
function extrairTextoSimples($html) {
    $html = strip_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/\s+/', ' ', $html); // normaliza espaços
    return trim($html);
}

// Processa cada página
$resultados = [];

foreach ($paginas as $pagina) {
    $html = @file_get_contents($pagina);
    if (!$html) {
        $resultados[] = ['pagina' => $pagina, 'status' => 'erro ao acessar'];
        continue;
    }

    $texto = extrairTextoSimples($html);

    foreach ($palavras_chave as $palavra) {
        if (stripos($texto, $palavra) !== false) {
            // Captura contexto com a palavra-chave
            preg_match_all('/.{0,80}' . preg_quote($palavra, '/') . '.{0,80}/i', $texto, $matches);

            foreach ($matches[0] as $trecho) {
                $pergunta = ucfirst(substr($trecho, 0, 100)) . '?';
                $resposta = $trecho;

                // Verifica se já existe pergunta parecida
                $check = $pdo->prepare("SELECT id FROM perguntas_respostas WHERE pergunta LIKE :pergunta");
                $check->execute([':pergunta' => '%' . substr($pergunta, 0, 50) . '%']);
                if ($check->fetch()) continue;

                // Insere no banco
                $insert = $pdo->prepare("INSERT INTO perguntas_respostas (pergunta, resposta, fonte, tema, prioridade) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([
                    $pergunta,
                    $resposta,
                    $pagina,
                    'AutoScraper',
                    1
                ]);

                $resultados[] = ['pagina' => $pagina, 'palavra' => $palavra, 'status' => 'adicionado', 'pergunta' => $pergunta];
            }
        }
    }
}

// Saída JSON resumida
echo json_encode([
    'status' => 'ok',
    'total_registros' => count($resultados),
    'detalhes' => $resultados
], JSON_PRETTY_PRINT);
