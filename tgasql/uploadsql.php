<?php
header('Content-Type: application/json; charset=utf-8');

// Função auxiliar para limpar nome de arquivo
function sanitizeFileName($name) {
    $name = preg_replace('/[^a-zA-Z0-9_\-]/u', '_', $name);
    return preg_replace('/_+/', '_', $name);
}

$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$texto = isset($_POST['texto']) ? trim($_POST['texto']) : '';
$dataHora = isset($_POST['dataHora']) ? $_POST['dataHora'] : '';
$arquivo = $_FILES['file'] ?? null;

if ($titulo === '') {
    echo json_encode(['erro' => 'O título é obrigatório.']);
    exit;
}

if ($texto === '' && (!$arquivo || $arquivo['error'] === UPLOAD_ERR_NO_FILE)) {
    echo json_encode(['erro' => 'Insira um texto ou envie um arquivo.']);
    exit;
}

// Cria pasta se não existir
$diretorio = __DIR__ . '/uploads/';
if (!is_dir($diretorio)) {
    mkdir($diretorio, 0777, true);
}

$nomeBase = sanitizeFileName($titulo);
$caminhoCompleto = $diretorio . $nomeBase . '.txt';

if ($texto !== '') {
    // Garante codificação correta
    $conteudo = mb_convert_encoding($texto, 'UTF-8', 'auto');
    file_put_contents($caminhoCompleto, $conteudo);
    echo json_encode(['sucesso' => true, 'mensagem' => 'Texto salvo com sucesso.']);
    exit;
}

if ($arquivo && $arquivo['error'] === UPLOAD_ERR_OK) {
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $extensao = strtolower($extensao);

    // Apenas .sql permitido
    if ($extensao !== 'sql') {
        echo json_encode(['erro' => 'Apenas arquivos .sql são permitidos.']);
        exit;
    }

    $conteudo = file_get_contents($arquivo['tmp_name']);
    $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'auto');

    file_put_contents($caminhoCompleto, $conteudo);
    echo json_encode(['sucesso' => true, 'mensagem' => 'Arquivo salvo com sucesso.']);
    exit;
}

echo json_encode(['erro' => 'Erro ao processar o envio.']);
