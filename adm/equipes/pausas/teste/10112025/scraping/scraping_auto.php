<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'auth.php';

function scrapeWithAutoAuth() {
    // SUAS CREDENCIAIS AQUI - PREENCHA COM SEUS DADOS REAIS
    $username = 'seu_usuario'; // TROQUE PELO SEU USUÁRIO
    $password = 'sua_senha';   // TROQUE PELA SUA SENHA
    
    $auth = new EvoluxAuth($username, $password);
    
    // Verificar se já está logado
    if (!$auth->isLoggedIn()) {
        file_put_contents('auth_log.txt', date('Y-m-d H:i:s') . " - Fazendo novo login\n", FILE_APPEND);
        
        if (!$auth->login()) {
            return ['error' => 'Falha no login automático - verifique credenciais'];
        }
    }
    
    // Obter cookies atualizados
    $cookies = $auth->getCookiesFromFile();
    if (!$cookies) {
        return ['error' => 'Não foi possível obter cookies'];
    }
    
    return scrapePausedAgents($cookies);
}

function scrapePausedAgents($cookies) {
    $url = "https://tgasistemas.evolux.io/panel/queue?id=all";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_COOKIE => $cookies,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP Error: $httpCode - Sessão expirada"];
    }
    
    return parseAgentsFromHTML($html);
}

function parseAgentsFromHTML($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $agents = [];
    
    $pausedSection = $xpath->query('//div[contains(@id, "panel-paused-agents")]//table/tbody/tr');
    
    foreach ($pausedSection as $row) {
        $nameElement = $xpath->query('.//td[2]//a|.//td[2]//span', $row)->item(0);
        $reasonElement = $xpath->query('.//td[3]', $row)->item(0);
        $durationElement = $xpath->query('.//td[4]//timer/span', $row)->item(0);
        
        if ($nameElement && $reasonElement && $durationElement) {
            $agents[] = [
                'name' => trim($nameElement->textContent),
                'reason' => trim($reasonElement->textContent),
                'duration' => trim($durationElement->textContent)
            ];
        }
    }
    
    return [
        'total_paused' => count($agents),
        'agents' => $agents,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Executar com autenticação automática
$result = scrapeWithAutoAuth();

// Log para debug
file_put_contents('scraping_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($result) . "\n", FILE_APPEND);

echo json_encode($result);
?>