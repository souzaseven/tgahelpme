<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getStoredCookies() {
    if (!file_exists('cookies.json')) {
        return ['error' => 'Nenhum cookie salvo. Use update_cookies.html primeiro.'];
    }
    
    $data = json_decode(file_get_contents('cookies.json'), true);
    
    // Verificar se os cookies são muito antigos (mais de 1 hora)
    if (time() - $data['timestamp'] > 3600) {
        return ['error' => 'Cookies expirados. Atualize em update_cookies.html'];
    }
    
    return $data['cookies'];
}

function scrapeWithStoredCookies() {
    $cookies = getStoredCookies();
    
    if (is_array($cookies) && isset($cookies['error'])) {
        return $cookies;
    }
    
    $url = "https://tgasistemas.evolux.io/panel/queue?id=all";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0',
        CURLOPT_COOKIE => $cookies,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => "HTTP Error $httpCode - Cookies podem ter expirado"];
    }
    
    if (strpos($html, 'login') !== false) {
        return ['error' => 'Redirecionado para login - cookies expirados. Atualize em update_cookies.html'];
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

// Executar
$result = scrapeWithStoredCookies();
echo json_encode($result);
?>  