<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pergunta = $input['pergunta'] ?? '';

if (!$pergunta) {
    echo json_encode(['resposta' => 'Pergunta não recebida.']);
    exit;
}

// Substitua por sua nova chave da API
//$apiKey = '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';
$apiKey = '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';


$payload = [
    'model' => 'gpt-3.5-turbo',

    'messages' => [
        ['role' => 'system', 'content' => 'Você é um atendente de help desk especializado no suporte técnico da plataforma TGame. Seu objetivo é fornecer respostas claras, objetivas e orientadas para a solução de erros, dúvidas e solicitações comuns de usuários. Baseie suas respostas no site tgameajuda.com e suas subpáginas.'],
        ['role' => 'user', 'content' => $pergunta]
    ]
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(['resposta' => 'Erro na requisição: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);
file_put_contents('log_resposta.txt', $response);

$result = json_decode($response, true);
/*
$resposta = $result['choices'][0]['message']['content'] ?? 'Nenhuma resposta gerada.';
echo json_encode(['resposta' => $resposta]);
*/
if (isset($result['choices'][0]['message']['content'])) {
    $resposta = $result['choices'][0]['message']['content'];
} elseif (isset($result['error']['message'])) {
    $resposta = 'Erro da API: ' . $result['error']['message'];
} else {
    $resposta = 'Resposta inesperada: ' . $response;
}
if (!$response) {
    echo json_encode(['resposta' => 'A resposta da API veio vazia.']);
    exit;
}
