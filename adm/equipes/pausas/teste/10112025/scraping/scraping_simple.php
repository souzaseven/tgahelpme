<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function scrapePausedAgents() {
    $url = "https://tgasistemas.evolux.io/panel/queue?id=all";
    
    // USE SEUS COOKIES AQUI - COLE A STRING QUE VOCÊ EXTRAIU
    $cookies = "evolux=a9915bb4025d3c89082b938faf9acc21b6d4bd8608a3b47bbaff40789cfbf49f7338d77e; authtkt_=2049e4c9463fa2f8f2645350d425b616688e8611p2!; hubspotutk=7196242df6f53a42be9430566f979411; __hssrc=1; messagesUtk=90238a82b38a4036b99f9872ea7d9c30; totango.heartbeat.last_module=__system; _clck=1vi2z9a%5E2%5Eg0w%5E0%5E1971; _clsk=wizag3%5E1762749314828%5E25%5E1%5Ei.clarity.ms%2Fcollect; __Host-csrf-token=3afc73c0cca843a7b3d4f90ea10145a8; totango.heartbeat.last_ts=1762784645164; __hstc=134485294.7196242df6f53a42be9430566f979411.1755129078672.1762778426773.1762784645620.46; __hssc=134485294.1.1762784645620";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0',
        CURLOPT_COOKIE => $cookies,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6'
        ]
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => 'Erro de conexão: ' . $error];
    }
    
    curl_close($ch);
    
    // Debug: salvar HTML para ver o que está vindo
    file_put_contents('debug_html.html', $html);
    
    if ($httpCode !== 200) {
        return ['error' => "Erro HTTP $httpCode - Cookies podem ter expirado"];
    }
    
    if (strlen($html) < 1000) {
        return ['error' => 'HTML muito curto - provavelmente redirecionado para login'];
    }
    
    // Verificar se é página de login
    if (strpos($html, 'login') !== false || strpos($html, 'entrar') !== false) {
        return ['error' => 'Redirecionado para página de login - cookies expirados'];
    }
    
    return parseAgentsFromHTML($html);
}

function parseAgentsFromHTML($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    // Converter para UTF-8
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    @$dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $agents = [];
    
    // Tentar diferentes seletores para encontrar os operadores pausados
    $selectors = [
        '//div[contains(@id, "panel-paused-agents")]//table/tbody/tr',
        '//table//tr[.//td[contains(text(), "Lanche") or contains(text(), "Pausa")]]',
        '//tr[.//timer]' // Linhas que contêm timer
    ];
    
    foreach ($selectors as $selector) {
        $rows = $xpath->query($selector);
        if ($rows->length > 0) {
            foreach ($rows as $row) {
                $name = $xpath->query('.//td[2]//a|.//td[2]//span', $row)->item(0);
                $reason = $xpath->query('.//td[3]', $row)->item(0);
                $duration = $xpath->query('.//td[4]//timer/span', $row)->item(0);
                
                if ($name && $reason && $duration) {
                    $agentName = trim($name->textContent);
                    $agentReason = trim($reason->textContent);
                    $agentDuration = trim($duration->textContent);
                    
                    // Só adicionar se tiver dados válidos
                    if (!empty($agentName) && $agentName !== 'N/A') {
                        $agents[] = [
                            'name' => $agentName,
                            'reason' => $agentReason,
                            'duration' => $agentDuration
                        ];
                    }
                }
            }
            break; // Parar no primeiro seletor que funcionar
        }
    }
    
    return [
        'total_paused' => count($agents),
        'agents' => $agents,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => 'HTML length: ' . strlen($html)
    ];
}

// Executar e retornar JSON
$result = scrapePausedAgents();

// Log para debug
file_put_contents('scraping_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($result) . "\n", FILE_APPEND);

echo json_encode($result);
?>