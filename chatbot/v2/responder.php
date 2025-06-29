<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🔗 CONEXÃO COM O BANCO
$host = getenv('DB_HOST') ?: '108.167.151.50';
$dbname = getenv('DB_NAME') ?: 'tgamea80_SUPORTE';
$user = getenv('DB_USER') ?: 'tgamea80_tgamea80';
$password = getenv('DB_PASS') ?: 'anderson@2250';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT => 5,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_PERSISTENT => false
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password, $options);
} catch (PDOException $e) {
    error_log("[ERRO CONEXAO PDO] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['resposta' => 'Erro na conexão com o banco de dados']);
    exit;
}

// 📥 RECEBE A PERGUNTA
$pergunta = trim($_POST['pergunta'] ?? '');
$nome = trim($_POST['nome'] ?? 'chat');

if (empty($pergunta)) {
    echo json_encode(['resposta' => 'Pergunta inválida ou vazia.']);
    exit;
}

// 🧠 DETECÇÃO DE TEMA
$tema_sugerido = null;
$temas = [
    'SEFAZ' => ['sefaz', 'rejeição', 'nfe', 'xml', 'nota fiscal'],
    'ERRO' => ['erro', 'problema', 'falha', 'bug'],
    'IMPRESSÃO' => ['impressora', 'danfe', 'não imprime', 'cupom'],
    'FIREBIRD' => ['firebird', 'banco', 'fbclient', 'servidor'],
    'CONFIGURAÇÃO' => ['configurar', 'parametro', 'setup', 'iniciar']
];
foreach ($temas as $tema => $palavras) {
    foreach ($palavras as $p) {
        if (stripos($pergunta, $p) !== false) {
            $tema_sugerido = $tema;
            break 2;
        }
    }
}

// 🔍 BUSCA EM RESPOSTAS EXISTENTES
try {
    $query = "SELECT id, resposta FROM perguntas_respostas 
              WHERE CONVERT(pergunta USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :busca 
              " . ($tema_sugerido ? "AND tema = :tema" : "") . "
              ORDER BY prioridade DESC, criado_em DESC 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $busca = '%' . $pergunta . '%';
    $stmt->bindParam(':busca', $busca);
    if ($tema_sugerido) {
        $stmt->bindParam(':tema', $tema_sugerido);
    }
    $stmt->execute();
    $resultado = $stmt->fetch();

    if ($resultado && !empty($resultado['resposta'])) {
        $pdo->prepare("INSERT INTO perguntas_logs (pergunta_id, data_acesso) VALUES (?, NOW())")
            ->execute([$resultado['id']]);

        echo json_encode(['resposta' => $resultado['resposta']]);
        exit;
    }
} catch (PDOException $e) {
    error_log("[ERRO PDO] " . $e->getMessage());
    echo json_encode(['resposta' => 'Erro interno ao buscar resposta.']);
    exit;
}

// 🤖 INTEGRAÇÃO COM IA DEEPSEEK
$apiKey = 'sk-8dfc8e0d544c4f15a848d6da11022c17';
$logPath = __DIR__ . '/log_debug.txt';

$payload = [
    'model' => 'deepseek-chat',
    'messages' => [
        ['role' => 'user', 'content' => $pergunta]
    ]
];

$curl = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$respostaApi = curl_exec($curl);
$erroApi = curl_error($curl);
curl_close($curl);

file_put_contents($logPath, date('Y-m-d H:i:s') . "\n" . ($respostaApi ?: $erroApi) . "\n\n", FILE_APPEND);

// 🔍 Trata possíveis erros
if ($erroApi) {
    error_log('[DEEPSEEK] cURL ERROR: ' . $erroApi);
} else {
    $json = json_decode($respostaApi, true);

    // 🧾 Verifica saldo insuficiente
    if (isset($json['error']['message']) && stripos($json['error']['message'], 'insufficient balance') !== false) {
        error_log('[DEEPSEEK] SALDO INSUFICIENTE');
        echo json_encode(['resposta' => 'Por gentileza, informe ao Anderson que os créditos chegaram ao fim.']);
        exit;
    }

    // ✅ Resposta válida
    if (!empty($json['choices'][0]['message']['content'])) {
        $respostaIA = $json['choices'][0]['message']['content'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO perguntas_respostas (pergunta, resposta, tema, fonte, criado_em)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$pergunta, $respostaIA, $tema_sugerido ?: null, 'DeepSeek']);

            $id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO perguntas_logs (pergunta_id, data_acesso) VALUES (?, NOW())")
                ->execute([$id]);
        } catch (PDOException $e) {
            error_log('[ERRO SALVAR RESPOSTA IA] ' . $e->getMessage());
        }

        echo json_encode(['resposta' => $respostaIA]);
        exit;
    } else {
        $msg = $json['error']['message'] ?? 'Resposta malformada.';
        error_log('[DEEPSEEK] RESPOSTA INVÁLIDA: ' . $respostaApi);
        echo json_encode(['resposta' => 'Erro da IA: ' . $msg]);
        exit;
    }
}

// ❌ FALLBACK: REGISTRA COMO PENDENTE
try {
    $pdo->prepare("INSERT INTO perguntas_pendentes (pergunta, origem) VALUES (?, ?)")
        ->execute([$pergunta, $nome]);
} catch (PDOException $e) {
    error_log("[ERRO INSERT PENDENTE] " . $e->getMessage());
}

echo json_encode([
    'resposta' => 'Ainda não sei responder, mas estou aprendendo. Em breve trarei uma solução.'
]);
exit;
