<?php
header('Content-Type: application/json');

if (!isset($_POST['url'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetro de URL ausente.']);
    exit;
}

// Extrai o ID do vídeo do YouTube
$url = $_POST['url'];
if (preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11})/', $url, $matches)) {
    $video_id = $matches[1];
} else {
    echo json_encode(['success' => false, 'error' => 'URL inválida.']);
    exit;
}

// Faz requisição para a nova API
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://youtube-mp36.p.rapidapi.com/dl?id=$video_id",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        "x-rapidapi-host: youtube-mp36.p.rapidapi.com",
        "x-rapidapi-key: 65686d176cmsh4fc5fa91b1d2506p1b297ajsn40d80440f554"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(['success' => false, 'error' => 'Erro cURL: ' . $err]);
    exit;
}

$data = json_decode($response, true);

// Verifica se a resposta tem o link
if (isset($data['status']) && $data['status'] === 'ok' && !empty($data['link'])) {
    echo json_encode([
        'success' => true,
        'file' => $data['link'],
        'title' => $data['title']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Erro: Não foi possível obter o link de download.',
        'api_response' => $data
    ]);
}
?>
